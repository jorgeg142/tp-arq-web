<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ReglasAsignacionService;

class ReglasAsignacionController extends Controller
{
    public function __construct(private ReglasAsignacionService $service) {}

    public function index(Request $request)
    {
        // tu índice original no paginaba; si querés, podés aceptar ?per_page
        $perPage = (int) $request->get('per_page', 0);
        $rules   = $this->service->listar($perPage > 0 ? $perPage : null);

        return view('reglas.index', compact('rules'));
    }

    public function create()
    {
        return view('reglas.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        try {
            $this->service->crear($data);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['limite_inferior' => $e->getMessage()])->withInput();
        }

        return redirect()->route('reglas.index')->with('ok', 'Regla creada.');
    }

    public function show(int $id)
    {
        $r = $this->service->obtener($id);
        abort_unless($r, 404);
        return view('reglas.show', compact('r'));
    }

    public function edit(int $id)
    {
        $r = $this->service->obtener($id);
        abort_unless($r, 404);
        return view('reglas.edit', compact('r'));
    }

    public function update(Request $request, int $id)
    {
        $exists = $this->service->obtener($id);
        abort_unless($exists, 404);

        $data = $this->validated($request);

        try {
            $this->service->actualizar($id, $data);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['limite_inferior' => $e->getMessage()])->withInput();
        }

        return redirect()->route('reglas.index')->with('ok', 'Regla actualizada.');
    }

    public function destroy(int $id)
    {
        $this->service->eliminar($id);
        return redirect()->route('reglas.index')->with('ok', 'Regla eliminada.');
    }

    // ------- Helpers -------

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'limite_inferior'    => ['required','numeric','min:0'],
            'limite_superior'    => ['nullable','numeric','gt:limite_inferior'],
            'monto_equivalencia' => ['required','numeric','gt:0'],
            'descripcion'        => ['nullable','string','max:200'],
            'prioridad'          => ['required','integer','min:1','max:100'],
            'activo'             => ['nullable'],
        ]);

        $data['activo'] = $request->boolean('activo') ? 1 : 0;
        if ($data['limite_superior'] === null || $data['limite_superior'] === '') {
            $data['limite_superior'] = null; // ∞
        }

        return $data;
    }
}
