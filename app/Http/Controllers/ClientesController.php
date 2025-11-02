<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientesController extends Controller
{
    // LISTADO + búsqueda + filtro
    public function index(Request $request)
    {
        $q       = trim($request->get('q', ''));
        $estado  = $request->get('estado'); // '1','0' o null
        $perPage = (int) $request->get('per_page', 10) ?: 10;

        $clientes = DB::table('clientes as c')
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
            ->paginate($perPage)
            ->appends($request->query());

        return view('clientes.index', compact('clientes','q','estado','perPage'));
    }

    // FORM CREAR
    public function create()
    {
        return view('clientes.create');
    }

    // INSERT
    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::table('clientes')->insert([
            'nombre'           => $data['nombre'],
            'apellido'         => $data['apellido'],
            'numero_documento' => $data['numero_documento'] ?? null,
            'tipo_documento'   => $data['tipo_documento']   ?? null,
            'nacionalidad'     => $data['nacionalidad']     ?? null,
            'email'            => $data['email']            ?? null,
            'telefono'         => $data['telefono']         ?? null,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'activo'           => isset($data['activo']) ? 1 : 0,
        ]);

        return redirect()->route('clientes.index')->with('ok','Cliente creado.');
    }

    // VER
    public function show(int $id)
    {
        $c = DB::table('clientes')->where('id',$id)->first();
        abort_unless($c, 404);
        return view('clientes.show', compact('c'));
    }

    // FORM EDITAR
    public function edit(int $id)
    {
        $c = DB::table('clientes')->where('id',$id)->first();
        abort_unless($c, 404);
        return view('clientes.edit', compact('c'));
    }

    // UPDATE
    public function update(Request $request, int $id)
    {
        $c = DB::table('clientes')->where('id',$id)->first();
        abort_unless($c, 404);

        $data = $this->validated($request, $id);

        DB::table('clientes')->where('id',$id)->update([
            'nombre'           => $data['nombre'],
            'apellido'         => $data['apellido'],
            'numero_documento' => $data['numero_documento'] ?? null,
            'tipo_documento'   => $data['tipo_documento']   ?? null,
            'nacionalidad'     => $data['nacionalidad']     ?? null,
            'email'            => $data['email']            ?? null,
            'telefono'         => $data['telefono']         ?? null,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'activo'           => isset($data['activo']) ? 1 : 0,
        ]);

        return redirect()->route('clientes.index')->with('ok','Cliente actualizado.');
    }

    // DELETE
    public function destroy(int $id)
    {
        DB::table('clientes')->where('id',$id)->delete();
        return redirect()->route('clientes.index')->with('ok','Cliente eliminado.');
    }

    // ---- helpers ----
    private function validated(Request $request, ?int $id = null): array
    {
        $uniqueDoc = 'unique:clientes,numero_documento';
        $uniqueMail = 'unique:clientes,email';

        if ($id) {
            $uniqueDoc .= ',' . $id;
            $uniqueMail .= ',' . $id;
        }

        return $request->validate([
            'nombre'           => ['required','string','max:100'],
            'apellido'         => ['required','string','max:100'],
            'numero_documento' => ['nullable','string','max:50', $uniqueDoc],
            'tipo_documento'   => ['nullable','string','max:30'],
            'nacionalidad'     => ['nullable','string','max:50'],
            'email'            => ['nullable','email','max:150', $uniqueMail],
            'telefono'         => ['nullable','string','max:50'],
            'fecha_nacimiento' => ['nullable','date'],
            'activo'           => ['nullable'], // checkbox
        ]);
    }
}
