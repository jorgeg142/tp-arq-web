<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConceptosUsoService
{
    // -------- Lectura --------

    public function hasCategoria(): bool
    {
        return Schema::hasColumn('conceptos_uso', 'categoria');
    }

    public function listar(string $q = '', $estado = null, int $perPage = 10)
    {
        $hasCat = $this->hasCategoria();

        $select = ['id','descripcion_concepto','puntos_requeridos','activo'];
        if ($hasCat) { $select[] = 'categoria'; }

        $query = DB::table('conceptos_uso')
            ->select($select)
            ->when($q !== '', function ($sql) use ($q) {
                $like = '%'.$q.'%';
                $sql->where('descripcion_concepto','like',$like);
            })
            ->when($estado !== null && $estado !== '', fn($sql) => $sql->where('activo',(int)$estado))
            ->orderBy('descripcion_concepto');

        return $query->paginate($perPage);
    }

    public function kpis(): array
    {
        $hasCat = $this->hasCategoria();
        $total     = (int) DB::table('conceptos_uso')->count();
        $activos   = (int) DB::table('conceptos_uso')->where('activo',1)->count();
        $inactivos = $total - $activos;
        $byCategoria = $hasCat
            ? DB::table('conceptos_uso')
                ->select('categoria', DB::raw('COUNT(*) as cant'))
                ->groupBy('categoria')
                ->pluck('cant','categoria')
            : collect();

        return compact('total','activos','inactivos','byCategoria') + ['hasCategoria'=>$hasCat];
    }

    public function obtener(int $id): ?object
    {
        return DB::table('conceptos_uso')->where('id',$id)->first();
    }

    public function categoriasPreset(): array
    {
        // solo sugerencias de UI
        return ['Descuento','Producto','Servicio','Consumo'];
    }

    // -------- Escritura --------

    public function crear(array $data): int
    {
        $hasCat = $this->hasCategoria();
        $row = [
            'descripcion_concepto' => $data['descripcion_concepto'],
            'puntos_requeridos'    => $data['puntos_requeridos'],
            'activo'               => !empty($data['activo']) ? 1 : 0,
        ];
        if ($hasCat) { $row['categoria'] = $data['categoria'] ?? null; }

        return (int) DB::table('conceptos_uso')->insertGetId($row);
    }

    public function actualizar(int $id, array $data): void
    {
        $hasCat = $this->hasCategoria();
        $row = [
            'descripcion_concepto' => $data['descripcion_concepto'],
            'puntos_requeridos'    => $data['puntos_requeridos'],
            'activo'               => !empty($data['activo']) ? 1 : 0,
        ];
        if ($hasCat) { $row['categoria'] = $data['categoria'] ?? null; }

        DB::table('conceptos_uso')->where('id',$id)->update($row);
    }

    public function eliminar(int $id): void
    {
        DB::table('conceptos_uso')->where('id',$id)->delete();
    }
}
