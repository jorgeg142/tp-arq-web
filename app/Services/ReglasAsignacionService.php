<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReglasAsignacionService
{
    // ------------------- Lectura / Listado -------------------

    public function listar(?int $perPage = null)
    {
        $rules = DB::table('reglas_asignacion')
            ->orderBy('prioridad')
            ->orderBy('limite_inferior');

        if ($perPage && $perPage > 0) {
            $pag = $rules->paginate($perPage);
            // decorar items manteniendo la paginación
            $baseEq = $this->baseEq();
            $pag->setCollection(
                $pag->getCollection()->map(fn($r) => $this->decorate($r, $baseEq))
            );
            return $pag;
        }

        $rules = $rules->get();
        $baseEq = $this->baseEq();
        return $rules->map(fn($r) => $this->decorate($r, $baseEq));
    }

    public function obtener(int $id): ?object
    {
        $r = DB::table('reglas_asignacion')->where('id', $id)->first();
        if (!$r) return null;

        $baseEq = $this->baseEq();
        return $this->decorate($r, $baseEq);
    }

    // ------------------- Escritura -------------------

    public function crear(array $data): int
    {
        if (($data['activo'] ?? 0) == 1 && $this->overlaps(null, $data['limite_inferior'], $data['limite_superior'])) {
            throw new \RuntimeException('El rango se solapa con otra regla activa.');
        }

        return (int) DB::table('reglas_asignacion')->insertGetId([
            'limite_inferior'    => $data['limite_inferior'],
            'limite_superior'    => $data['limite_superior'],
            'monto_equivalencia' => $data['monto_equivalencia'],
            'descripcion'        => $data['descripcion'] ?? null,
            'prioridad'          => $data['prioridad'],
            'activo'             => $data['activo'] ?? 0,
        ]);
    }

    public function actualizar(int $id, array $data): void
    {
        if (($data['activo'] ?? 0) == 1 && $this->overlaps($id, $data['limite_inferior'], $data['limite_superior'])) {
            throw new \RuntimeException('El rango se solapa con otra regla activa.');
        }

        DB::table('reglas_asignacion')->where('id', $id)->update([
            'limite_inferior'    => $data['limite_inferior'],
            'limite_superior'    => $data['limite_superior'],
            'monto_equivalencia' => $data['monto_equivalencia'],
            'descripcion'        => $data['descripcion'] ?? null,
            'prioridad'          => $data['prioridad'],
            'activo'             => $data['activo'] ?? 0,
        ]);
    }

    public function eliminar(int $id): void
    {
        DB::table('reglas_asignacion')->where('id', $id)->delete();
    }

    // ------------------- Utilitarios / Lógica de negocio -------------------

    public function overlaps(?int $ignoreId, float $low, $highNullable): bool
    {
        $high = $highNullable; // null = +∞

        $q = DB::table('reglas_asignacion')->where('activo', 1);
        if ($ignoreId) $q->where('id', '<>', $ignoreId);

        // (a,b] se solapa con (c,d] si a < d && c < b (con NULL como +∞)
        return $q->where(function ($w) use ($low, $high) {
            $w->where('limite_inferior', '<', $high ?? 9e18)
              ->where(function ($y) use ($low) {
                  $y->whereNull('limite_superior')
                    ->orWhere('limite_superior', '>', $low);
              });
        })->exists();
    }

    public function kpis(): array
    {
        $totales = (int) DB::table('reglas_asignacion')->count();
        $activas = (int) DB::table('reglas_asignacion')->where('activo', 1)->count();
        $minEq   = (float) (DB::table('reglas_asignacion')->min('monto_equivalencia') ?? 0);
        return compact('totales', 'activas', 'minEq');
    }

    // ------------------- Privados -------------------

    private function baseEq(): int
    {
        $min = DB::table('reglas_asignacion')->min('monto_equivalencia');
        $min = is_null($min) ? 1 : $min;
        return max(1, (int) floor($min ?: 1));
    }

    private function decorate(object $r, int $baseEq): object
    {
        $factor    = $r->monto_equivalencia > 0 ? round($baseEq / (float) $r->monto_equivalencia, 1) : 1;
        $r->ratio_x = $factor; // ej: 1.5x
        $r->rango   = $this->rangeLabel($r->limite_inferior, $r->limite_superior);
        $r->eq_text = '1 punto cada ' . $this->fmt2($r->monto_equivalencia);
        return $r;
    }

    private function rangeLabel($low, $high): string
    {
        $a = '$' . $this->fmt2($low);
        if ($high === null) return $a . ' - ∞';
        $b = '$' . $this->fmt2($high);
        return $a . ' - ' . $b;
    }

    private function fmt2($n): string
    {
        return rtrim(rtrim(number_format((float)$n, 2, '.', ','), '0'), '.');
    }
}
