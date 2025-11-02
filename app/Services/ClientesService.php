<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ClientesService
{
    // LISTADO con búsqueda, filtro y saldo desde vista
    public function listar(string $q = '', $estado = null, int $perPage = 10)
    {
        return DB::table('clientes as c')
            ->leftJoin('vw_saldo_puntos_cliente as v', 'v.cliente_id', '=', 'c.id')
            ->select(
                'c.id','c.nombre','c.apellido','c.numero_documento','c.tipo_documento',
                'c.nacionalidad','c.email','c.telefono','c.fecha_nacimiento','c.activo',
                DB::raw('COALESCE(v.saldo_total,0) as puntos')
            )
            ->when($q !== '', function ($sql) use ($q) {
                $like = '%'.$q.'%';
                $sql->where(function($w) use ($like) {
                    $w->where('c.nombre','like',$like)
                      ->orWhere('c.apellido','like',$like)
                      ->orWhere('c.email','like',$like)
                      ->orWhere('c.numero_documento','like',$like);
                });
            })
            ->when($estado !== null && $estado !== '', fn($sql) => $sql->where('c.activo',(int)$estado))
            ->orderBy('c.apellido')->orderBy('c.nombre')
            ->paginate($perPage);
    }

    // OBTENER un cliente (sin saldo)
    public function obtener(int $id): ?object
    {
        return DB::table('clientes')->where('id',$id)->first();
    }

    // CREAR
    public function crear(array $data): int
    {
        return (int) DB::table('clientes')->insertGetId([
            'nombre'           => $data['nombre'],
            'apellido'         => $data['apellido'],
            'numero_documento' => $data['numero_documento'] ?? null,
            'tipo_documento'   => $data['tipo_documento']   ?? null,
            'nacionalidad'     => $data['nacionalidad']     ?? null,
            'email'            => $data['email']            ?? null,
            'telefono'         => $data['telefono']         ?? null,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'activo'           => !empty($data['activo']) ? 1 : 0,
        ]);
    }

    // ACTUALIZAR
    public function actualizar(int $id, array $data): void
    {
        DB::table('clientes')->where('id',$id)->update([
            'nombre'           => $data['nombre'],
            'apellido'         => $data['apellido'],
            'numero_documento' => $data['numero_documento'] ?? null,
            'tipo_documento'   => $data['tipo_documento']   ?? null,
            'nacionalidad'     => $data['nacionalidad']     ?? null,
            'email'            => $data['email']            ?? null,
            'telefono'         => $data['telefono']         ?? null,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'activo'           => !empty($data['activo']) ? 1 : 0,
        ]);
    }

    // ELIMINAR
    public function eliminar(int $id): void
    {
        DB::table('clientes')->where('id',$id)->delete();
    }
}
