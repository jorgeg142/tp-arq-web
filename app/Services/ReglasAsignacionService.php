<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReglasAsignacionService
{
    // ------------------- Lectura / Listado -------------------

    public function listar(?int $perPage = null)
    {
        $rules = DB::table('reglas_asignacion')
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

     public function assertNoOverlap(array $data, ?int $ignoreId = null): void
    {
        $li = (float)$data['limite_inferior'];
        $ls = isset($data['limite_superior']) && $data['limite_superior'] !== '' ? (float)$data['limite_superior'] : null;

        // Validación básica de bordes
        if (!is_null($ls) && $ls < $li) {
            throw ValidationException::withMessages([
                'limite_superior' => 'El límite superior debe ser mayor o igual al inferior.',
            ]);
        }

        // Si la nueva/actualizada es global (ls = NULL): solo puede existir UNA global
        if (is_null($ls)) {
            $q = DB::table('reglas_asignacion')
                ->when($ignoreId, fn($x) => $x->where('id', '!=', $ignoreId))
                ->whereNull('limite_superior');

            if ($q->exists()) {
                throw ValidationException::withMessages([
                    'limite_superior' => 'Ya existe una regla global. Solo se permite una.',
                ]);
            }
            return; // global nunca “se solapa” con específicas según nuestro criterio
        }

        // Chequear superposición con otras ESPECÍFICAS
        $overlap = DB::table('reglas_asignacion')
            ->when($ignoreId, fn($x) => $x->where('id', '!=', $ignoreId))
            ->whereNotNull('limite_superior')
            ->where(function ($q) use ($li, $ls) {
                // Solapadas si: li <= ls_existente  AND  li_existente <= ls
                $q->where(function ($qq) use ($li, $ls) {
                    $qq->where('limite_inferior', '<=', $ls)
                       ->where('limite_superior', '>=', $li);
                });
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'limite_inferior' => 'El rango se superpone con otra regla existente.',
                'limite_superior' => 'El rango se superpone con otra regla existente.',
            ]);
        }
    }

    public function crear(array $data): int
    {
        $this->assertNoOverlap($data, null);

        return (int) DB::table('reglas_asignacion')->insertGetId([
            'descripcion'        => $data['descripcion'] ?? null,
            'limite_inferior'    => $data['limite_inferior'],
            'limite_superior'    => $data['limite_superior'] !== '' ? $data['limite_superior'] : null,
            'monto_equivalencia' => $data['monto_equivalencia'],
            'activo'             => !empty($data['activo']) ? 1 : 0,
            // 'prioridad'        => eliminado: ya no se usa
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function actualizar(int $id, array $data): void
    {
        $this->assertNoOverlap($data, $id);

        DB::table('reglas_asignacion')->where('id', $id)->update([
            'descripcion'        => $data['descripcion'] ?? null,
            'limite_inferior'    => $data['limite_inferior'],
            'limite_superior'    => $data['limite_superior'] !== '' ? $data['limite_superior'] : null,
            'monto_equivalencia' => $data['monto_equivalencia'],
            'activo'             => !empty($data['activo']) ? 1 : 0,
            'updated_at'         => now(),
        ]);
    }

    /** Útil para tests o para calcular “preview” de puntos desde PHP. */
    public function calcularPuntos(float $monto): int
    {
        return (int) DB::table(DB::raw('DUAL'))
            ->selectRaw('fn_calcular_puntos(?) as pts', [$monto])
            ->value('pts') ?? 0;
    }

    public function eliminar(int $id): void
    {
        DB::table('reglas_asignacion')->where('id', $id)->delete();
    }

    // ------------------- Utilitarios / Lógica de negocio -------------------
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
