<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ReglasAsignacionService;

class ReglasAsignacionApiController extends Controller
{
    public function __construct(private ReglasAsignacionService $service) {}

    // GET /api/reglas
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 0);

        if ($perPage > 0) {
            $rows = $this->service->listar($perPage);
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

        $rows = $this->service->listar(null);
        return response()->json([
            'data' => $rows,
            'kpis' => $this->service->kpis(),
        ]);
    }

    // POST /api/reglas
    public function store(Request $request)
    {
        $data = $this->validateData($request);

        try {
            $id = $this->service->crear($data);
            return response()->json([
                'ok'  => true,
                'id'  => $id,
                'row' => $this->service->obtener($id),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 422);
        }
    }

    // GET /api/reglas/{id}
    public function show(int $id)
    {
        $row = $this->service->obtener($id);
        if (!$row) return response()->json(['ok'=>false,'error'=>'Not Found'], 404);
        return response()->json(['ok'=>true,'row'=>$row]);
    }

    // PUT /api/reglas/{id}
    public function update(Request $request, int $id)
    {
        if (!$this->service->obtener($id)) {
            return response()->json(['ok'=>false,'error'=>'Not Found'], 404);
        }

        $data = $this->validateData($request);

        try {
            $this->service->actualizar($id, $data);
            return response()->json(['ok'=>true,'row'=>$this->service->obtener($id)]);
        } catch (\RuntimeException $e) {
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 422);
        }
    }

    // DELETE /api/reglas/{id}
    public function destroy(int $id)
    {
        if (!$this->service->obtener($id)) {
            return response()->json(['ok'=>false,'error'=>'Not Found'], 404);
        }

        $this->service->eliminar($id);
        return response()->json(['ok'=>true]);
    }

    // GET /api/reglas/overlaps?low=...&high=...&ignore_id=...
    public function overlaps(Request $request)
    {
        $low       = (float) $request->query('low');
        $highParam = $request->query('high'); // puede venir null
        $ignoreId  = $request->query('ignore_id') ? (int) $request->query('ignore_id') : null;

        $high = ($highParam === null || $highParam === '') ? null : (float) $highParam;

        $solapa = $this->service->overlaps($ignoreId, $low, $high);
        return response()->json(['overlaps' => $solapa]);
    }

    private function validateData(Request $request): array
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
            $data['limite_superior'] = null;
        }
        return $data;
    }
}
