<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

class UsosService
{
    public function kpis(array $filters = []): array
    {
        $today = Carbon::today()->toDateString();

        $canjesHoy       = (int) DB::table('uso_puntos_cab')
                                ->whereDate('fecha', $today)->count();

        $totalCanjeados  = (int) DB::table('uso_puntos_cab')->sum('puntaje_utilizado');

        $pendientes      = (int) DB::table('uso_puntos_cab')
                                ->where('estado','PENDIENTE')->count();

        $promedio        = (int) (DB::table('uso_puntos_cab')->avg('puntaje_utilizado') ?: 0);

        return compact('canjesHoy','totalCanjeados','pendientes','promedio');
    }

    public function listar(string $q = '', ?string $estado = null, int $perPage = 10)
    {
        return DB::table('uso_puntos_cab as u')
            ->join('clientes as c','c.id','=','u.cliente_id')
            ->leftJoin('conceptos_uso as cu','cu.id','=','u.concepto_uso_id')
            ->select(
                'u.id','u.puntaje_utilizado','u.fecha','u.estado','u.comprobante',
                'c.id as cliente_id','c.nombre','c.apellido',
                DB::raw("COALESCE(cu.descripcion_concepto, CONCAT('Concepto #',u.concepto_uso_id)) as concepto")
            )
            ->when($q !== '', function($sql) use ($q){
                $like = '%'.$q.'%';
                $sql->where(function($w) use ($like){
                    $w->where('c.nombre','like',$like)
                      ->orWhere('c.apellido','like',$like)
                      ->orWhere('u.comprobante','like',$like)
                      ->orWhere('cu.descripcion_concepto','like',$like);
                });
            })
            ->when($estado, fn($sql)=>$sql->where('u.estado',$estado))
            ->orderByDesc('u.fecha')
            ->paginate($perPage);
    }

    public function datosCreate(): array
    {
        $clientes  = DB::table('clientes')->where('activo',1)->orderBy('apellido')->get();
        $conceptos = DB::table('conceptos_uso')->where('activo',1)->orderBy('descripcion_concepto')->get();
        return compact('clientes','conceptos');
    }

    public function saldoDisponible(int $clienteId): int
    {
        return (int) DB::table('bolsas_puntos')
            ->where('cliente_id',$clienteId)
            ->where('saldo_puntos','>',0)
            ->where(function($w){
                $w->whereNull('fecha_caducidad')
                  ->orWhere('fecha_caducidad','>=', now()->toDateString());
            })->sum('saldo_puntos');
    }

    public function crearCanje(array $data): int
    {
        // Resolver puntos a usar
        if ($data['modo'] === 'concepto') {
            $req = DB::table('conceptos_uso')->where('id',$data['concepto_uso_id'])->value('puntos_requeridos');
            if (!$req) {
                throw new \InvalidArgumentException('Concepto no válido');
            }
            $puntos = (int)$req;
        } else {
            if (empty($data['puntos'])) {
                throw new \InvalidArgumentException('Ingresá los puntos a usar');
            }
            $puntos = (int)$data['puntos'];
        }

        // Verificación local de saldo (el SP también valida)
        $saldo = $this->saldoDisponible((int)$data['cliente_id']);
        if ($saldo < $puntos) {
            throw new \RuntimeException("Puntos insuficientes. Saldo disponible: {$saldo}.");
        }

        // Ejecutar SP FIFO dentro de transacción
        return DB::transaction(function() use ($data, $puntos) {
            try {
                DB::statement('CALL sp_usar_puntos_fifo(?,?,?)', [
                    $data['cliente_id'],
                    $puntos,
                    $data['modo']==='concepto' ? $data['concepto_uso_id'] : 0
                ]);
            } catch (QueryException $e) {
                // re-lanzamos, lo atrapará el caller
                throw $e;
            }

            // Si llegó comprobante, actualizamos el último cab de ese cliente
            if (!empty($data['comprobante'])) {
                $lastId = DB::table('uso_puntos_cab')
                    ->where('cliente_id',$data['cliente_id'])
                    ->max('id');

                if ($lastId) {
                    DB::table('uso_puntos_cab')->where('id',$lastId)
                        ->update(['comprobante'=>$data['comprobante']]);
                }

                return (int) $lastId;
            }

            // Retornamos el último id generado para ese cliente (cabecera creada por el SP)
            return (int) DB::table('uso_puntos_cab')
                ->where('cliente_id',$data['cliente_id'])
                ->max('id');
        });
    }

    public function obtenerCabeceraYDetalle(int $id): array
    {
        $cab = DB::table('uso_puntos_cab as u')
            ->join('clientes as c','c.id','=','u.cliente_id')
            ->leftJoin('conceptos_uso as cu','cu.id','=','u.concepto_uso_id')
            ->select(
                'u.*',
                'c.nombre','c.apellido','c.numero_documento',
                DB::raw("COALESCE(cu.descripcion_concepto, CONCAT('Concepto #',u.concepto_uso_id)) as concepto")
            )->where('u.id',$id)->first();

        if (!$cab) {
            abort(404);
        }

        $det = DB::table('uso_puntos_det as d')
            ->join('bolsas_puntos as b','b.id','=','d.bolsa_id')
            ->select('d.puntaje_utilizado','d.fecha_detalle','b.fecha_asignacion','b.fecha_caducidad','b.origen')
            ->where('d.cabecera_id',$id)
            ->orderBy('d.id')->get();

        return ['cabecera'=>$cab,'detalles'=>$det];
    }

    public function editarDatos(int $id): array
    {
        $cab = DB::table('uso_puntos_cab as u')
            ->join('clientes as c','c.id','=','u.cliente_id')
            ->leftJoin('conceptos_uso as cu','cu.id','=','u.concepto_uso_id')
            ->select(
                'u.*',
                'c.nombre','c.apellido',
                DB::raw("COALESCE(cu.descripcion_concepto, CONCAT('Concepto #',u.concepto_uso_id)) as concepto")
            )->where('u.id',$id)->first();

        if (!$cab) abort(404);

        $estados = ['COMPLETADO' => 'Completado', 'PENDIENTE' => 'Pendiente', 'ANULADO' => 'Anulado'];
        return compact('cab','estados');
    }

    public function actualizar(int $id, array $data): void
    {
        $exists = DB::table('uso_puntos_cab')->where('id',$id)->exists();
        if (!$exists) abort(404);

        DB::table('uso_puntos_cab')->where('id',$id)->update([
            'estado'      => $data['estado'],
            'comprobante' => $data['comprobante'] ?? null,
        ]);
    }

    public function eliminar(int $id): void
    {
        $det = DB::table('uso_puntos_det')->where('cabecera_id',$id)->count();
        if ($det > 0) {
            throw new \RuntimeException(
                "No se puede eliminar el canje porque ya impactó en bolsas (tiene {$det} detalle(s)). ".
                "Si necesitás revertir, implementemos una anulación con reversa."
            );
        }

        DB::table('uso_puntos_cab')->where('id',$id)->delete();
    }
}
