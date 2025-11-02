<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ConceptosUsoService;

class ConceptosUsoApiController extends Controller
{
    public function __construct(private ConceptosUsoService $svc) {}

    // GET /api/conceptos?q=&estado=&per_page=10
    public function index(Request $request)
    {
        $q       = trim($request->query('q',''));
        $estado  = $request->query('estado');
        $perPage = (int) $request->query('per_page', 10) ?: 10;

        $rows = $this->svc->listar($q, $estado, $perPage);
        $kpis = $this->svc->kpis();

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
            'kpis' => $kpis,
        ]);
    }

    // GET /api/conceptos/{id}
    public function show(int $id)
    {
        $row = $this->svc->obtener($id);
        if (!$row) return response()->json(['ok'=>false,'error'=>'Not Found'], 404);
        return response()->json(['ok'=>true,'row'=>$row]);
    }

    // POST /api/conceptos
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

        $id = $this->svc->crear($data);
        return response()->json(['ok'=>true,'id'=>$id,'row'=>$this->svc->obtener($id)], 201);
    }

    // PUT /api/conceptos/{id}
    public function update(Request $request, int $id)
    {
        if (!$this->svc->obtener($id)) {
            return response()->json(['ok'=>false,'error'=>'Not Found'], 404);
        }

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
        return response()->json(['ok'=>true,'row'=>$this->svc->obtener($id)]);
    }

    // DELETE /api/conceptos/{id}
    public function destroy(int $id)
    {
        if (!$this->svc->obtener($id)) {
            return response()->json(['ok'=>false,'error'=>'Not Found'], 404);
        }
        $this->svc->eliminar($id);
        return response()->json(['ok'=>true]);
    }

    // GET /api/conceptos/categorias
    public function categorias()
    {
        if (!$this->svc->hasCategoria()) {
            return response()->json(['ok'=>true,'hasCategoria'=>false,'categorias'=>[]]);
        }
        return response()->json([
            'ok'=>true,
            'hasCategoria'=>true,
            'categorias'=>$this->svc->categoriasPreset()
        ]);
    }
}
