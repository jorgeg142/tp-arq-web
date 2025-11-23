<?php

namespace App\Http\Controllers;

use App\Services\NivelesService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NivelesController extends Controller
{
    public function __construct(private NivelesService $svc) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10) ?: 10;
        $niveles = $this->svc->paginar($perPage)->appends($request->query());

        return view('niveles.index', compact('niveles', 'perPage'));
    }

    public function create()
    {
        return view('niveles.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->svc->crear($data);

        return redirect()->route('niveles.index')->with('ok', 'Nivel creado correctamente.');
    }

    public function edit(int $id)
    {
        $nivel = $this->svc->obtener($id);
        abort_unless($nivel, 404);

        return view('niveles.edit', compact('nivel'));
    }

    public function update(Request $request, int $id)
    {
        $nivel = $this->svc->obtener($id);
        abort_unless($nivel, 404);

        $data = $this->validated($request, $id);
        $this->svc->actualizar($id, $data);

        return redirect()->route('niveles.index')->with('ok', 'Nivel actualizado.');
    }

    public function destroy(int $id)
    {
        $this->svc->eliminar($id);

        return redirect()->route('niveles.index')->with('ok', 'Nivel eliminado.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'nombre'      => ['required', 'string', 'max:120'],
            'slug'        => ['nullable', 'string', 'max:120', Rule::unique('niveles', 'slug')->ignore($id)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'min_puntos'  => ['required', 'integer', 'min:0'],
            'max_puntos'  => ['nullable', 'integer', 'gt:min_puntos'],
            'beneficios'  => ['nullable', 'string', 'max:1000'],
        ]);

        return $data;
    }
}
