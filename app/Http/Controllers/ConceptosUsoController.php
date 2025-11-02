<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\ConceptosUsoService;

class ConceptosUsoController extends Controller
{
    public function __construct(private ConceptosUsoService $svc) {}

    public function index(Request $request)
    {
        $q       = trim($request->get('q',''));
        $estado  = $request->get('estado');
        $perPage = (int) $request->get('per_page', 10) ?: 10;

        $conceptos = $this->svc->listar($q, $estado, $perPage)->appends($request->query());
        $kpis      = $this->svc->kpis();

        return view('conceptos.index', [
            'conceptos'   => $conceptos,
            'q'           => $q,
            'estado'      => $estado,
            'perPage'     => $perPage,
        ] + $kpis);
    }

    public function create()
    {
        $hasCategoria = $this->svc->hasCategoria();
        $categorias   = $hasCategoria ? $this->svc->categoriasPreset() : [];
        return view('conceptos.create', compact('hasCategoria','categorias'));
    }

    public function store(Request $request)
    {
        $hasCategoria = $this->svc->hasCategoria();

        $rules = [
            'descripcion_concepto' => ['required','string','max:200','unique:conceptos_uso,descripcion_concepto'],
            'puntos_requeridos'    => ['required','integer','min:0'],
            'activo'               => ['nullable'],
        ];
        if ($hasCategoria) { $rules['categoria'] = ['nullable','string','max:50']; }

        $data = $request->validate($rules);
        $data['activo'] = $request->boolean('activo') ? 1 : 0;

        $this->svc->crear($data);
        return redirect()->route('conceptos.index')->with('ok','Concepto creado.');
    }

    public function show(int $id)
    {
        $c = $this->svc->obtener($id);
        abort_unless($c, 404);
        $hasCategoria = property_exists($c,'categoria');
        return view('conceptos.show', compact('c','hasCategoria'));
    }

    public function edit(int $id)
    {
        $c = $this->svc->obtener($id);
        abort_unless($c, 404);
        $hasCategoria = $this->svc->hasCategoria();
        $categorias   = $hasCategoria ? $this->svc->categoriasPreset() : [];
        return view('conceptos.edit', compact('c','hasCategoria','categorias'));
    }

    public function update(Request $request, int $id)
    {
        $exists = $this->svc->obtener($id);
        abort_unless($exists, 404);

        $hasCategoria = $this->svc->hasCategoria();

        $rules = [
            'descripcion_concepto' => ['required','string','max:200','unique:conceptos_uso,descripcion_concepto,'.$id],
            'puntos_requeridos'    => ['required','integer','min:0'],
            'activo'               => ['nullable'],
        ];
        if ($hasCategoria) { $rules['categoria'] = ['nullable','string','max:50']; }

        $data = $request->validate($rules);
        $data['activo'] = $request->boolean('activo') ? 1 : 0;

        $this->svc->actualizar($id, $data);
        return redirect()->route('conceptos.index')->with('ok','Concepto actualizado.');
    }

    public function destroy(int $id)
    {
        $this->svc->eliminar($id);
        return redirect()->route('conceptos.index')->with('ok','Concepto eliminado.');
    }
}
