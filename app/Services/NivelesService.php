<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NivelesService
{
    public function paginar(int $perPage = 10): LengthAwarePaginator
    {
        $niveles = DB::table('niveles')->orderBy('min_puntos')->paginate($perPage);

        $niveles->setCollection(
            $niveles->getCollection()->map(fn ($nivel) => (object) $this->mapNivel($nivel))
        );

        return $niveles;
    }

    public function listar(): array
    {
        return DB::table('niveles')
            ->orderBy('min_puntos')
            ->get()
            ->map(fn ($nivel) => $this->mapNivel($nivel))
            ->toArray();
    }

    public function obtener(int $id): ?object
    {
        $nivel = DB::table('niveles')->where('id', $id)->first();

        return $nivel ? (object) $this->mapNivel($nivel) : null;
    }

    public function crear(array $data): int
    {
        $payload = $this->buildPayload($data);

        return (int) DB::table('niveles')->insertGetId($payload);
    }

    public function actualizar(int $id, array $data): void
    {
        $payload = $this->buildPayload($data, true);

        DB::table('niveles')->where('id', $id)->update($payload);
    }

    public function eliminar(int $id): void
    {
        DB::table('clientes')->where('nivel_id', $id)->update(['nivel_id' => null]);
        DB::table('niveles')->where('id', $id)->delete();
    }

    public function nivelParaPuntos(int $puntos): array
    {
        $niveles = $this->listar();

        if (empty($niveles)) {
            return [
                'id'         => null,
                'clave'      => null,
                'nombre'     => 'Sin nivel configurado',
                'minimo'     => 0,
                'maximo'     => null,
                'beneficios' => [],
                'progreso'   => 0,
                'siguiente'  => null,
            ];
        }

        $actual = $niveles[0];
        $siguiente = $niveles[1] ?? null;

        foreach ($niveles as $i => $nivel) {
            if ($puntos >= $nivel['min_puntos']) {
                $actual = $nivel;
                $siguiente = $niveles[$i + 1] ?? null;
            }
        }

        return [
            'id'         => $actual['id'],
            'clave'      => $actual['slug'],
            'nombre'     => $actual['nombre'],
            'minimo'     => $actual['min_puntos'],
            'maximo'     => $actual['max_puntos'],
            'beneficios' => $actual['beneficios'],
            'progreso'   => $this->calcularProgreso($puntos, $actual, $siguiente),
            'siguiente'  => $siguiente ? [
                'id'      => $siguiente['id'],
                'nombre'  => $siguiente['nombre'],
                'minimo'  => $siguiente['min_puntos'],
                'maximo'  => $siguiente['max_puntos'],
                'faltan'  => max($siguiente['min_puntos'] - $puntos, 0),
            ] : null,
        ];
    }

    private function mapNivel(object $nivel): array
    {
        return [
            'id'           => $nivel->id,
            'slug'         => $nivel->slug,
            'nombre'       => $nivel->nombre,
            'descripcion'  => $nivel->descripcion,
            'min_puntos'   => (int) $nivel->min_puntos,
            'max_puntos'   => $nivel->max_puntos !== null ? (int) $nivel->max_puntos : null,
            'beneficios'   => $this->parseBeneficios($nivel->beneficios),
        ];
    }

    private function parseBeneficios(?string $beneficios): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $beneficios ?? ''))));
    }

    private function buildPayload(array $data, bool $forUpdate = false): array
    {
        $beneficios = trim($data['beneficios'] ?? '');
        $slug = $data['slug'] ?? '';

        $payload = [
            'nombre'      => $data['nombre'],
            'slug'        => $slug !== '' ? $slug : Str::slug($data['nombre']),
            'descripcion' => $data['descripcion'] ?? null,
            'min_puntos'  => (int) $data['min_puntos'],
            'max_puntos'  => isset($data['max_puntos']) && $data['max_puntos'] !== ''
                ? (int) $data['max_puntos']
                : null,
            'beneficios'  => $beneficios !== '' ? $beneficios : null,
            'updated_at'  => now(),
        ];

        if (! $forUpdate) {
            $payload['created_at'] = now();
        }

        return $payload;
    }

    private function calcularProgreso(int $puntos, array $actual, ?array $siguiente): int
    {
        if ($siguiente === null) {
            return 100;
        }

        $base = $actual['min_puntos'];
        $toNext = max($siguiente['min_puntos'] - $base, 1);
        $avance = max($puntos - $base, 0);

        return (int) min(100, round(($avance / $toNext) * 100));
    }
}
