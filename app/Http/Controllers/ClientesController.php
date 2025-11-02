<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ClientesService;

class ClientesController extends Controller
{
    public function __construct(private ClientesService $svc) {}

    public function index(Request $request)
    {
        $q       = trim($request->get('q', ''));
        $estado  = $request->get('estado');
        $perPage = (int) $request->get('per_page', 10) ?: 10;

        $clientes = $this->svc->listar($q, $estado, $perPage)->appends($request->query());
        return view('clientes.index', compact('clientes','q','estado','perPage'));
    }

    public function create()
    {
        return view('clientes.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['activo'] = $request->boolean('activo') ? 1 : 0;
        $this->svc->crear($data);

        return redirect()->route('clientes.index')->with('ok','Cliente creado.');
    }

    public function show(int $id)
    {
        $c = $this->svc->obtener($id);
        abort_unless($c, 404);
        return view('clientes.show', compact('c'));
    }

    public function edit(int $id)
    {
        $c = $this->svc->obtener($id);
        abort_unless($c, 404);
        return view('clientes.edit', compact('c'));
    }

    public function update(Request $request, int $id)
    {
        $c = $this->svc->obtener($id);
        abort_unless($c, 404);

        $data = $this->validated($request, $id);
        $data['activo'] = $request->boolean('activo') ? 1 : 0;

        $this->svc->actualizar($id, $data);
        return redirect()->route('clientes.index')->with('ok','Cliente actualizado.');
    }

    public function destroy(int $id)
    {
        $this->svc->eliminar($id);
        return redirect()->route('clientes.index')->with('ok','Cliente eliminado.');
    }
}
