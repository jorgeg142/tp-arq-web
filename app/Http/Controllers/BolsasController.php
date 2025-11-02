<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class BolsasController extends Controller
{
    public function index(Request $request)
    {
        $q        = trim($request->get('q', ''));
        $estado   = $request->get('estado');   // null|activo|vencido|agotado
        $cliente  = $request->get('cliente');  // id de cliente opcional
        $perPage  = (int) $request->get('per_page', 10) ?: 10;

        $today = Carbon::today()->toDateString();
        $in28  = Carbon::today()->addDays(28)->toDateString();

        // -------- KPIs --------
        $kpiActivos = (int) DB::table('bolsas_puntos')
            ->where(function($w){
                $w->whereNull('fecha_caducidad')
                  ->orWhere('fecha_caducidad','>=',now()->toDateString());
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
            ->where(function($w){
                $w->whereNull('fecha_caducidad')
                  ->orWhere('fecha_caducidad','>=',now()->toDateString());
            })->count();

        // -------- Gráfico: evolución últimos 6 meses --------
        $start = Carbon::today()->startOfMonth()->subMonths(5);
        $end   = Carbon::today()->endOfMonth();

        $evol = DB::table('bolsas_puntos')
            ->selectRaw("DATE_FORMAT(fecha_asignacion, '%Y-%m') as ym,
                         SUM(puntaje_asignado) as asignados,
                         SUM(puntaje_utilizado) as usados")
            ->whereBetween('fecha_asignacion', [$start, $end])
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        // rellenar meses faltantes
        $labels = [];
        $asig   = [];
        $used   = [];
        for ($d = $start->copy(); $d <= $end; $d->addMonth()) {
            $ym = $d->format('Y-m');
            $labels[] = $d->isoFormat('MMM');
            $row = $evol->firstWhere('ym',$ym);
            $asig[] = $row->asignados ?? 0;
            $used[] = $row->usados ?? 0;
        }

        // -------- Top por cliente --------
        $topClientes = DB::table('bolsas_puntos as b')
            ->join('clientes as c','c.id','=','b.cliente_id')
            ->select('c.id','c.nombre','c.apellido', DB::raw('SUM(b.saldo_puntos) as saldo'))
            ->groupBy('c.id','c.nombre','c.apellido')
            ->orderByDesc('saldo')
            ->limit(5)
            ->get();

        // -------- Listado con filtros --------
        $rows = DB::table('bolsas_puntos as b')
            ->join('clientes as c','c.id','=','b.cliente_id')
            ->select(
                'b.*',
                'c.nombre','c.apellido',
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
            ->paginate($perPage)
            ->appends($request->query());

        // clientes para combo
        $clientes = DB::table('clientes')->select('id','nombre','apellido')->orderBy('apellido')->get();

        return view('bolsas.index', compact(
            'rows','clientes','cliente','estado','q','perPage',
            'kpiActivos','kpiUtilizados','kpiPorVencer','kpiBolsasActivas',
            'labels','asig','used','topClientes'
        ));
    }
    public function show(int $id)
    {
        $today = \Carbon\Carbon::today()->toDateString();

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
            )
            ->where('b.id',$id)->first();

        abort_unless($bolsa, 404);

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

        return response()->json([
            'bolsa'    => $bolsa,
            'detalles' => $detalles,
        ]);
    }

    public function export(Request $request)
    {
        $today   = \Carbon\Carbon::today()->toDateString();
        $q       = trim($request->get('q', ''));
        $estado  = $request->get('estado');
        $cliente = $request->get('cliente');

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

        // --- Armado del XLSX ---
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Bolsa de Puntos');

        // Encabezados
        $headers = [
            'A1' => 'Cliente',
            'B1' => 'Fecha Asignación',
            'C1' => 'Fecha Vencimiento',
            'D1' => 'Puntos Asignados',
            'E1' => 'Puntos Usados',
            'F1' => 'Puntos Restantes',
            'G1' => 'Monto Operación',
            'H1' => 'Origen',
            'I1' => 'Estado',
        ];
        foreach ($headers as $cell => $text) { $sheet->setCellValue($cell, $text); }
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        // Datos
        $r = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$r}", "{$row->nombre} {$row->apellido}");
            // fechas como texto para no pelear formato regional (o podés mapear a DateTime)
            $sheet->setCellValueExplicit("B{$r}", $row->fecha_asignacion, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$r}", $row->fecha_caducidad ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("D{$r}", (int)$row->puntaje_asignado);
            $sheet->setCellValue("E{$r}", (int)$row->puntaje_utilizado);
            $sheet->setCellValue("F{$r}", (int)$row->saldo_puntos);
            $sheet->setCellValue("G{$r}", (float)$row->monto_operacion);
            $sheet->setCellValue("H{$r}", $row->origen ?? '');
            $sheet->setCellValue("I{$r}", $row->estado);
            $r++;
        }

        // Formatos
        $sheet->getStyle("D2:F{$r}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
        $sheet->getStyle("G2:G{$r}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        // Auto-ancho
        foreach (range('A','I') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

        // Stream de salida
        $writer = new Xlsx($ss);
        $filename = 'bolsa_puntos_'.now()->format('Ymd_His').'.xlsx';

        // Enviar como descarga
        return response()->streamDownload(function() use ($writer){
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function create()
    {
        $clientes  = DB::table('clientes')->where('activo',1)->orderBy('apellido')->get();
        $params    = DB::table('param_vencimientos')->where('activo',1)->orderByDesc('fecha_inicio_validez')->get();
        return view('bolsas.create', compact('clientes','params'));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'cliente_id'          => ['required','integer','exists:clientes,id'],
            'fecha_asignacion'    => ['required','date'],
            'param_vencimiento_id'=> ['nullable','integer','exists:param_vencimientos,id'],
            'fecha_caducidad'     => ['nullable','date'],
            'puntaje_asignado'    => ['required','integer','min:0'],
            'puntaje_utilizado'   => ['nullable','integer','min:0'],
            'saldo_puntos'        => ['nullable','integer','min:0'],
            'monto_operacion'     => ['nullable','numeric','min:0'],
            'origen'              => ['nullable','string','max:100'],
            'auto_por_monto'      => ['nullable','boolean'],
        ]);

        // Si se marca "auto_por_monto", calculamos puntos con fn_calcular_puntos(monto)
        if ($request->boolean('auto_por_monto')) {
            $pts = DB::table(DB::raw('DUAL'))
                     ->selectRaw('fn_calcular_puntos(?) as pts', [$v['monto_operacion'] ?? 0])->value('pts');
            $v['puntaje_asignado']  = (int) $pts;
        }

        $v['puntaje_utilizado'] = (int)($v['puntaje_utilizado'] ?? 0);
        $v['saldo_puntos']      = $v['saldo_puntos'] ?? max(0, ($v['puntaje_asignado'] - $v['puntaje_utilizado']));

        // Si hay parámetro sin fecha explícita, proyectar caducidad
        if (empty($v['fecha_caducidad']) && !empty($v['param_vencimiento_id'])) {
            $dias = DB::table('param_vencimientos')->where('id',$v['param_vencimiento_id'])->value('dias_duracion');
            if (!is_null($dias)) {
                $v['fecha_caducidad'] = \Carbon\Carbon::parse($v['fecha_asignacion'])->addDays($dias)->toDateString();
            }
        }

        DB::table('bolsas_puntos')->insert([
            'cliente_id'          => $v['cliente_id'],
            'fecha_asignacion'    => $v['fecha_asignacion'],
            'fecha_caducidad'     => $v['fecha_caducidad'] ?? null,
            'puntaje_asignado'    => $v['puntaje_asignado'],
            'puntaje_utilizado'   => $v['puntaje_utilizado'],
            'saldo_puntos'        => $v['saldo_puntos'],
            'monto_operacion'     => $v['monto_operacion'] ?? 0,
            'origen'              => $v['origen'] ?? null,
            'param_vencimiento_id'=> $v['param_vencimiento_id'] ?? null,
        ]);

        return redirect()->route('bolsas.index')->with('ok','Bolsa creada correctamente.');
    }

    public function edit(int $id)
    {
        $row = DB::table('bolsas_puntos')->where('id',$id)->first();
        abort_unless($row, 404);
        $clientes = DB::table('clientes')->orderBy('apellido')->get();
        $params   = DB::table('param_vencimientos')->orderByDesc('fecha_inicio_validez')->get();
        return view('bolsas.edit', compact('row','clientes','params'));
    }

    public function update(Request $request, int $id)
    {
        $row = DB::table('bolsas_puntos')->where('id',$id)->first();
        abort_unless($row, 404);

        $v = $request->validate([
            'cliente_id'          => ['required','integer','exists:clientes,id'],
            'fecha_asignacion'    => ['required','date'],
            'param_vencimiento_id'=> ['nullable','integer','exists:param_vencimientos,id'],
            'fecha_caducidad'     => ['nullable','date'],
            'puntaje_asignado'    => ['required','integer','min:0'],
            'puntaje_utilizado'   => ['required','integer','min:0'],
            'saldo_puntos'        => ['required','integer','min:0'],
            'monto_operacion'     => ['nullable','numeric','min:0'],
            'origen'              => ['nullable','string','max:100'],
        ]);

        // Coherencia básica: asignado = utilizado + saldo  (opcionalmente forzamos)
        if ($v['puntaje_asignado'] !== ($v['puntaje_utilizado'] + $v['saldo_puntos'])) {
            return back()->withErrors(['puntaje_asignado' => 'Asignado debe ser igual a Usado + Saldo.'])
                         ->withInput();
        }

        // Si hay parámetro y no se envió fecha cad., recalcular
        if (empty($v['fecha_caducidad']) && !empty($v['param_vencimiento_id'])) {
            $dias = DB::table('param_vencimientos')->where('id',$v['param_vencimiento_id'])->value('dias_duracion');
            if (!is_null($dias)) {
                $v['fecha_caducidad'] = \Carbon\Carbon::parse($v['fecha_asignacion'])->addDays($dias)->toDateString();
            }
        }

        DB::table('bolsas_puntos')->where('id',$id)->update([
            'cliente_id'          => $v['cliente_id'],
            'fecha_asignacion'    => $v['fecha_asignacion'],
            'fecha_caducidad'     => $v['fecha_caducidad'] ?? null,
            'puntaje_asignado'    => $v['puntaje_asignado'],
            'puntaje_utilizado'   => $v['puntaje_utilizado'],
            'saldo_puntos'        => $v['saldo_puntos'],
            'monto_operacion'     => $v['monto_operacion'] ?? 0,
            'origen'              => $v['origen'] ?? null,
            'param_vencimiento_id'=> $v['param_vencimiento_id'] ?? null,
        ]);

        return redirect()->route('bolsas.index')->with('ok','Bolsa actualizada.');
    }

    public function destroy(int $id)
    {
        // Evitar borrar si tuvo usos (opcional: tu FK está en CASCADE)
        $usos = DB::table('uso_puntos_det')->where('bolsa_id',$id)->count();
        if ($usos > 0) {
            return back()->withErrors(['del'=>"No se puede eliminar: la bolsa tiene usos asociados ({$usos})."]);
        }
        DB::table('bolsas_puntos')->where('id',$id)->delete();
        return back()->with('ok','Bolsa eliminada.');
    }
}
