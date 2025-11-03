<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    private function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'nombre'            => ['required','string','max:120'],
            'apellido'          => ['nullable','string','max:120'],

            // Ajustá si "numero_documento" NO es único en tu tabla
            'numero_documento'  => [
                'required','string','max:50',
                Rule::unique('clientes','numero_documento')->ignore($id),
            ],

            // Si tenés catálogo de tipos, podés usar Rule::in(['CI','RUC','PAS'])
            'tipo_documento'    => ['nullable','string','max:20'],

            'nacionalidad'      => ['nullable','string','max:80'],

            // Quitá el unique si tu columna email no es única
            'email'             => [
                'nullable','email','max:255',
                Rule::unique('clientes','email')->ignore($id),
            ],

            'telefono'          => ['nullable','string','max:50'],

            // Si el input viene como YYYY-MM-DD dejá 'date'; si viene DD/MM/YYYY, cambiá a 'date_format:d/m/Y'
            'fecha_nacimiento'  => ['nullable','date'], // o: ['nullable','date_format:Y-m-d']
            // 'activo' se maneja fuera con $request->boolean('activo')
        ]);

        // Normalizaciones útiles
        // Si te llega '' en lugar de null para fecha, lo convertimos a null:
        if (array_key_exists('fecha_nacimiento', $data) && $data['fecha_nacimiento'] === '') {
            $data['fecha_nacimiento'] = null;
        }

        return $data;
    }
}
