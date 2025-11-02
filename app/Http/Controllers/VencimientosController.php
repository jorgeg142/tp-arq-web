<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\VencimientosService;

class VencimientosController extends Controller
{
    public function __construct(private VencimientosService $service) {}

    public function index(Request $request)
    {
        $q       = trim($request->get('q', ''));
        $perPage = (int) $request->get('per_page', 10) ?: 10;

        $periodos = $this->service->listar($q, $perPage)->appends($request->query());
        $kpis     = $this->service->kpis();

        return view('vencimientos.index', [
            'periodos' => $periodos,
            'q'        => $q,
            'perPage'  => $perPage,
        ] + $kpis);
    }

    public function create()
    {
        return view('vencimientos.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->service->crear($data);
        return redirect()->route('vencimientos.index')->with('ok','Período creado.');
    }

    public function show(int $id)
    {
        $p = $this->service->obtener($id);
        abort_unless($p, 404);

        $impacto = $this->service->resumenImpacto($id);
        return view('vencimientos.show', ['p'=>$p] + $impacto);
    }

    public function edit(int $id)
    {
        $p = $this->service->obtener($id);
        abort_unless($p, 404);
        return view('vencimientos.edit', compact('p'));
    }

    public function update(Request $request, int $id)
    {
        $exists = (bool) $this->service->obtener($id);
        abort_unless($exists, 404);

        $data = $this->validated($request);
        $this->service->actualizar($id, $data);

        return redirect()->route('vencimientos.index')->with('ok','Período actualizado.');
    }

    public function destroy(int $id)
    {
        $this->service->eliminar($id);
        return redirect()->route('vencimientos.index')->with('ok','Período eliminado.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'fecha_inicio_validez' => ['required','date'],
            'fecha_fin_validez'    => ['nullable','date','after_or_equal:fecha_inicio_validez'],
            'dias_duracion'        => ['required','integer','min:0'],
            'descripcion'          => ['nullable','string','max:200'],
            'activo'               => ['nullable'],
        ]);

        $data['activo'] = $request->boolean('activo') ? 1 : 0;
        if (empty($data['fecha_fin_validez'])) $data['fecha_fin_validez'] = null;

        return $data;
    }
}
