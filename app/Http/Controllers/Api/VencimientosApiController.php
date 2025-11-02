<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VencimientosService;

class VencimientosApiController extends Controller
{
    public function __construct(private VencimientosService $service) {}

    // GET /api/vencimientos
    public function index(Request $request)
    {
        $q       = trim($request->get('q',''));
        $perPage = (int) $request->get('per_page', 10) ?: 10;

        $rows = $this->service->listar($q, $perPage);
        $kpis = $this->service->kpis();

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

    // POST /api/vencimientos
    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $id   = $this->service->crear($data);

        return response()->json([
            'ok'  => true,
            'id'  => $id,
            'row' => $this->service->obtener($id),
        ], 201);
    }

    // GET /api/vencimientos/{id}
    public function show(int $id)
    {
        $row = $this->service->obtener($id);
        if (!$row) return response()->json(['ok'=>false,'error'=>'Not Found'], 404);

        $impacto = $this->service->resumenImpacto($id);
        return response()->json(['ok'=>true,'row'=>$row] + $impacto);
    }

    // PUT /api/vencimientos/{id}
    public function update(Request $request, int $id)
    {
        if (!$this->service->obtener($id)) {
            return response()->json(['ok'=>false,'error'=>'Not Found'], 404);
        }

        $data = $this->validateData($request);
        $this->service->actualizar($id, $data);

        return response()->json(['ok'=>true,'row'=>$this->service->obtener($id)]);
    }

    // DELETE /api/vencimientos/{id}
    public function destroy(int $id)
    {
        if (!$this->service->obtener($id)) {
            return response()->json(['ok'=>false,'error'=>'Not Found'], 404);
        }

        $this->service->eliminar($id);
        return response()->json(['ok'=>true]);
    }

    private function validateData(Request $request): array
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
