<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VencimientosService
{
    public function kpis(): array
    {
        $today = Carbon::today()->toDateString();

        $periodosActivos = (int) DB::table('param_vencimientos')->where('activo',1)->count();

        $puntosAfectados = (int) DB::table('bolsas_puntos')->sum('puntaje_asignado');

        $proximosAVencer = (int) DB::table('bolsas_puntos')
            ->whereNotNull('fecha_caducidad')
            ->where('saldo_puntos','>',0)
            ->whereBetween('fecha_caducidad', [$today, Carbon::today()->addDays(28)->toDateString()])
            ->count();

        return compact('periodosActivos','puntosAfectados','proximosAVencer');
    }

    public function listar(string $q = '', int $perPage = 10)
    {
        return DB::table('param_vencimientos as p')
            ->leftJoin('bolsas_puntos as b', 'b.param_vencimiento_id', '=', 'p.id')
            ->select(
                'p.id','p.fecha_inicio_validez','p.fecha_fin_validez',
                'p.dias_duracion','p.descripcion','p.activo',
                DB::raw('COALESCE(SUM(b.puntaje_asignado),0) as puntos_afectados')
            )
            ->when($q !== '', fn($sql)=>$sql->where('p.descripcion','like','%'.$q.'%'))
            ->groupBy('p.id','p.fecha_inicio_validez','p.fecha_fin_validez','p.dias_duracion','p.descripcion','p.activo')
            ->orderByDesc('p.fecha_inicio_validez')
            ->paginate($perPage);
    }

    public function crear(array $data): int
    {
        return (int) DB::table('param_vencimientos')->insertGetId([
            'fecha_inicio_validez' => $data['fecha_inicio_validez'],
            'fecha_fin_validez'    => $data['fecha_fin_validez'] ?? null,
            'dias_duracion'        => $data['dias_duracion'],
            'descripcion'          => $data['descripcion'] ?? null,
            'activo'               => $data['activo'] ?? 0,
        ]);
    }

    public function obtener(int $id): ?object
    {
        return DB::table('param_vencimientos')->where('id',$id)->first();
    }

    public function resumenImpacto(int $id): array
    {
        $puntos = (int) DB::table('bolsas_puntos')->where('param_vencimiento_id',$id)->sum('puntaje_asignado');
        $bolsas = (int) DB::table('bolsas_puntos')->where('param_vencimiento_id',$id)->count();
        return compact('puntos','bolsas');
    }

    public function actualizar(int $id, array $data): void
    {
        DB::table('param_vencimientos')->where('id',$id)->update([
            'fecha_inicio_validez' => $data['fecha_inicio_validez'],
            'fecha_fin_validez'    => $data['fecha_fin_validez'] ?? null,
            'dias_duracion'        => $data['dias_duracion'],
            'descripcion'          => $data['descripcion'] ?? null,
            'activo'               => $data['activo'] ?? 0,
        ]);
    }

    public function eliminar(int $id): void
    {
        DB::table('param_vencimientos')->where('id',$id)->delete();
    }
}
