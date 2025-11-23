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

    public function segmentacion(Request $request)
    {
        $filters = $request->validate([
            'q'            => ['nullable','string','max:120'],
            'estado'       => ['nullable','in:0,1'],
            'edad_min'     => ['nullable','integer','min:0','max:120'],
            'edad_max'     => ['nullable','integer','min:0','max:120'],
            'nacionalidad' => ['nullable','string','max:80'],
            'monto_min'    => ['nullable','numeric','min:0'],
            'monto_max'    => ['nullable','numeric','min:0'],
            'compras_min'  => ['nullable','integer','min:0'],
            'puntos_min'   => ['nullable','integer','min:0'],
            'orden'        => ['nullable','in:monto_desc,compras_desc,puntos_desc,edad_desc,recientes'],
            'per_page'     => ['nullable','integer','in:10,25,50'],
        ]);

        $filters['orden'] = $filters['orden'] ?? 'monto_desc';
        $filters['q']     = $filters['q'] ?? '';

        $perPage  = (int) ($filters['per_page'] ?? 10) ?: 10;
        $clientes = $this->svc->segmentar($filters, $perPage)->appends($request->query());

        $nacionalidades = $this->svc->nacionalidades();

        return view('clientes.segmentacion', [
            'clientes'       => $clientes,
            'filters'        => $filters,
            'nacionalidades' => $nacionalidades,
            'perPage'        => $perPage,
        ]);
    }

    public function create()
    {
        return view('clientes.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['activo'] = $request->boolean('activo') ? 1 : 0;
        $codigoReferente = $request->get('codigo_referente');
        $this->svc->crear($data, $codigoReferente ?: null);

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
        unset($data['codigo_referente']);

        $this->svc->actualizar($id, $data);
        return redirect()->route('clientes.index')->with('ok','Cliente actualizado.');
    }

    public function destroy(int $id)
    {
        $this->svc->eliminar($id);
        return redirect()->route('clientes.index')->with('ok','Cliente eliminado.');
    }

    public function referidos(int $id)
    {
        $c = $this->svc->obtener($id);
        abort_unless($c, 404);

        $defaults = $this->svc->bonosReferenciaPorDefecto();

        return view('clientes.referidos', compact('c','defaults'));
    }

    public function actualizarReferidos(Request $request, int $id)
    {
        $c = $this->svc->obtener($id);
        abort_unless($c, 404);

        $data = $request->validate([
            'codigo_referido'    => ['nullable','string','max:16', Rule::unique('clientes','codigo_referido')->ignore($id)],
            'puntos_por_referir' => ['nullable','integer','min:0'],
            'puntos_bienvenida'  => ['nullable','integer','min:0'],
        ]);

        $this->svc->actualizarReferidos($id, $data);

        return redirect()->route('clientes.referidos', $id)->with('ok','Datos de referido guardados.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        if ($request->filled('codigo_referente')) {
            $request->merge(['codigo_referente' => trim($request->input('codigo_referente'))]);
        } else {
            $request->merge(['codigo_referente' => null]);
        }

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
            'codigo_referente'  => ['nullable','string','max:16','exists:clientes,codigo_referido'],
        ]);

        // Normalizaciones útiles
        // Si te llega '' en lugar de null para fecha, lo convertimos a null:
        if (array_key_exists('fecha_nacimiento', $data) && $data['fecha_nacimiento'] === '') {
            $data['fecha_nacimiento'] = null;
        }

        if (array_key_exists('codigo_referente', $data) && $data['codigo_referente'] === '') {
            $data['codigo_referente'] = null;
        }

        return $data;
    }
}
