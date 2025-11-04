<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class BolsasService
{
    // ---------- KPIs ----------
    public function kpis(): array
    {
        $today = Carbon::today()->toDateString();
        $in28  = Carbon::today()->addDays(28)->toDateString();

        $kpiActivos = (int) DB::table('bolsas_puntos')
            ->where(function($w) use ($today){
                $w->whereNull('fecha_caducidad')
                  ->orWhere('fecha_caducidad','>=',$today);
            })
            ->sum('saldo_puntos');

        $kpiUtilizados = (int) DB::table('bolsas_puntos')->sum('puntaje_utilizado');

        $kpiPorVencer = (int) DB::table('bolsas_puntos')
            ->whereNotNull('fecha_caducidad')
            ->where('saldo_puntos','>',0)
            ->whereBetween('fecha_caducidad', [$today, $in28])
            ->count();

        $kpiBolsasActivas = (int) DB::table('bolsas_puntos')
            ->where('saldo_puntos','>',0)
            ->where(function($w) use ($today){
                $w->whereNull('fecha_caducidad')
                  ->orWhere('fecha_caducidad','>=',$today);
            })->count();

        return compact('kpiActivos','kpiUtilizados','kpiPorVencer','kpiBolsasActivas');
    }

    // ---------- Serie últimos 6 meses ----------
    public function trend(int $months = 6): array
    {
        $start = Carbon::today()->startOfMonth()->subMonths($months-1);
        $end   = Carbon::today()->endOfMonth();

        $evol = DB::table('bolsas_puntos')
            ->selectRaw("DATE_FORMAT(fecha_asignacion, '%Y-%m') as ym,
                         SUM(puntaje_asignado) as asignados,
                         SUM(puntaje_utilizado) as usados")
            ->whereBetween('fecha_asignacion', [$start, $end])
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        $labels = []; $asig = []; $used = [];
        for ($d = $start->copy(); $d <= $end; $d->addMonth()) {
            $ym = $d->format('Y-m');
            $labels[] = $d->isoFormat('MMM');
            $row = $evol->firstWhere('ym',$ym);
            $asig[] = (int)($row->asignados ?? 0);
            $used[] = (int)($row->usados ?? 0);
        }
        return compact('labels','asig','used');
    }

    // ---------- Top clientes ----------
    public function topClientes(int $limit = 5)
    {
        return DB::table('bolsas_puntos as b')
            ->join('clientes as c','c.id','=','b.cliente_id')
            ->select('c.id','c.nombre','c.apellido', DB::raw('SUM(b.saldo_puntos) as saldo'))
            ->groupBy('c.id','c.nombre','c.apellido')
            ->orderByDesc('saldo')
            ->limit($limit)
            ->get();
    }

    // ---------- Listado ----------
    public function listar(string $q = '', $estado = null, $cliente = null, int $perPage = 10)
    {
        $today = Carbon::today()->toDateString();

        return DB::table('bolsas_puntos as b')
            ->join('clientes as c','c.id','=','b.cliente_id')
            ->select(
                'b.*','c.nombre','c.apellido',
                DB::raw("CASE
                           WHEN b.saldo_puntos = 0 THEN 'agotado'
                           WHEN b.fecha_caducidad IS NOT NULL AND b.fecha_caducidad < '$today' THEN 'vencido'
                           ELSE 'activo'
                         END AS estado_calc")
            )
            ->when($q !== '', function($sql) use ($q){
                $like = '%'.$q.'%';
                $sql->where(function($w) use ($like){
                    $w->where('c.nombre','like',$like)
                      ->orWhere('c.apellido','like',$like)
                      ->orWhere('b.origen','like',$like);
                });
            })
            ->when($cliente, fn($sql)=>$sql->where('b.cliente_id',(int)$cliente))
            ->when($estado,  fn($sql)=>$sql->having('estado_calc','=',$estado))
            ->orderByDesc('b.fecha_asignacion')
            ->paginate($perPage);
    }

    // ---------- Obtener bolsa + detalles ----------
    public function obtener(int $id): ?array
    {
        $today = Carbon::today()->toDateString();

        $bolsa = DB::table('bolsas_puntos as b')
            ->leftJoin('clientes as c','c.id','=','b.cliente_id')
            ->leftJoin('param_vencimientos as pv','pv.id','=','b.param_vencimiento_id')
            ->select(
                'b.*',
                'c.nombre','c.apellido','c.numero_documento',
                'pv.dias_duracion','pv.fecha_inicio_validez','pv.fecha_fin_validez',
                DB::raw("pv.descripcion as param_desc"),
                DB::raw("CASE
                    WHEN b.saldo_puntos = 0 THEN 'Agotado'
                    WHEN b.fecha_caducidad IS NOT NULL AND b.fecha_caducidad < '$today' THEN 'Vencido'
                    ELSE 'Activo' END as estado")
            )->where('b.id',$id)->first();

        if (!$bolsa) return null;

        $detalles = DB::table('uso_puntos_det as d')
            ->join('uso_puntos_cab as cab','cab.id','=','d.cabecera_id')
            ->leftJoin('conceptos_uso as cu','cu.id','=','cab.concepto_uso_id')
            ->select(
                'd.puntaje_utilizado','d.fecha_detalle',
                'cab.id as cabecera_id',
                DB::raw("COALESCE(cu.descripcion_concepto, CONCAT('Concepto #', cab.concepto_uso_id)) as concepto")
            )
            ->where('d.bolsa_id',$id)
            ->orderByDesc('d.fecha_detalle')
            ->get();

        return ['bolsa'=>$bolsa,'detalles'=>$detalles];
    }

    // ---------- Crear / Actualizar / Eliminar ----------
    public function crear(array $v): int
    {
        // 1) Calcular puntos asignados por reglas (siempre)
        $pts = DB::table(DB::raw('DUAL'))
        ->selectRaw('fn_calcular_puntos(?) AS pts', [$v['monto_operacion'] ?? 0])
        ->value('pts');

        $asignados  = (int)($pts ?? 0);
        $utilizado  = 0;                                  // <- fijo al crear
        $saldo      = max(0, $asignados - $utilizado);

        // 3) Caducidad automática si hay parámetro
        if (empty($v['fecha_caducidad']) && !empty($v['param_vencimiento_id'])) {
            $dias = DB::table('param_vencimientos')->where('id',$v['param_vencimiento_id'])->value('dias_duracion');
            if (!is_null($dias)) {
                $v['fecha_caducidad'] = \Carbon\Carbon::parse($v['fecha_asignacion'])->addDays($dias)->toDateString();
            }
        }

        // (opcional) bloquear asignaciones sin puntos
        // if ($v['puntaje_asignado'] <= 0) throw new \RuntimeException('No corresponde asignar puntos para ese monto.');

        return (int) DB::table('bolsas_puntos')->insertGetId([
        'cliente_id'           => $v['cliente_id'],
        'fecha_asignacion'     => $v['fecha_asignacion'],
        'fecha_caducidad'      => $v['fecha_caducidad'] ?? null,
        'puntaje_asignado'     => $asignados,
        'puntaje_utilizado'    => $utilizado,          // <- siempre 0
        'saldo_puntos'         => $saldo,              // <- derivado
        'monto_operacion'      => $v['monto_operacion'] ?? 0,
        'origen'               => $v['origen'] ?? null,
        'param_vencimiento_id' => $v['param_vencimiento_id'] ?? null,
        ]);
    }



    public function actualizar(int $id, array $v): void
    {
        if (empty($v['fecha_caducidad']) && !empty($v['param_vencimiento_id'])) {
            $dias = DB::table('param_vencimientos')->where('id',$v['param_vencimiento_id'])->value('dias_duracion');
            if (!is_null($dias)) {
                $v['fecha_caducidad'] = Carbon::parse($v['fecha_asignacion'])->addDays($dias)->toDateString();
            }
        }

        DB::table('bolsas_puntos')->where('id',$id)->update([
            'cliente_id'          => $v['cliente_id'],
            'fecha_asignacion'    => $v['fecha_asignacion'],
            'fecha_caducidad'     => $v['fecha_caducidad'] ?? null,
            'monto_operacion'     => $v['monto_operacion'] ?? 0,
            'origen'              => $v['origen'] ?? null,
            'param_vencimiento_id'=> $v['param_vencimiento_id'] ?? null,
        ]);
    }

    public function eliminar(int $id): void
    {
        DB::table('bolsas_puntos')->where('id',$id)->delete();
    }

    // ---------- Export (genera Spreadsheet listo para descargar) ----------
    public function buildExport(string $q = '', $estado = null, $cliente = null): Spreadsheet
    {
        $today = Carbon::today()->toDateString();

        $rows = DB::table('bolsas_puntos as b')
            ->join('clientes as c','c.id','=','b.cliente_id')
            ->select(
                'c.nombre','c.apellido','b.fecha_asignacion','b.fecha_caducidad',
                'b.puntaje_asignado','b.puntaje_utilizado','b.saldo_puntos',
                'b.monto_operacion','b.origen',
                DB::raw("CASE
                        WHEN b.saldo_puntos = 0 THEN 'Agotado'
                        WHEN b.fecha_caducidad IS NOT NULL AND b.fecha_caducidad < '$today' THEN 'Vencido'
                        ELSE 'Activo'
                        END AS estado")
            )
            ->when($q !== '', function($sql) use ($q){
                $like = '%'.$q.'%';
                $sql->where(function($w) use ($like){
                    $w->where('c.nombre','like',$like)
                      ->orWhere('c.apellido','like',$like)
                      ->orWhere('b.origen','like',$like);
                });
            })
            ->when($cliente, fn($sql)=>$sql->where('b.cliente_id',(int)$cliente))
            ->when($estado,  fn($sql)=>$sql->having('estado','=',$estado))
            ->orderByDesc('b.fecha_asignacion')
            ->get();

        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Bolsa de Puntos');

        $headers = ['Cliente','Fecha Asignación','Fecha Vencimiento','Puntos Asignados','Puntos Usados','Puntos Restantes','Monto Operación','Origen','Estado'];
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                "{$r->nombre} {$r->apellido}",
                $r->fecha_asignacion,
                $r->fecha_caducidad ?? '',
                (int)$r->puntaje_asignado,
                (int)$r->puntaje_utilizado,
                (int)$r->saldo_puntos,
                (float)$r->monto_operacion,
                $r->origen ?? '',
                $r->estado,
            ];
        }
        if ($data) $sheet->fromArray($data, null, 'A2');

        $last = count($data) + 1;
        $sheet->getStyle("D2:F{$last}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
        $sheet->getStyle("G2:G{$last}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        foreach (range('A','I') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

        return $ss;
    }
}
