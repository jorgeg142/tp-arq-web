<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use App\Services\UsosService;

class UsosApiController extends Controller
{
    public function __construct(private UsosService $service) {}

    // GET /api/usos
    public function index(Request $request)
    {
        $q       = trim($request->get('q',''));
        $estado  = $request->get('estado'); // COMPLETADO|PENDIENTE|null
        $perPage = (int) $request->get('per_page', 10) ?: 10;

        $rows = $this->service->listar($q, $estado, $perPage);
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

    // POST /api/usos
    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id'      => ['required','integer','exists:clientes,id'],
            'modo'            => ['required','in:concepto,libre'],
            'concepto_uso_id' => ['nullable','integer','exists:conceptos_uso,id'],
            'puntos'          => ['nullable','integer','min:1'],
            'comprobante'     => ['nullable','string','max:200'],
        ]);

        try {
            $id = $this->service->crearCanje($data);
            $payload = $this->service->obtenerCabeceraYDetalle($id);
            return response()->json(['ok'=>true,'id'=>$id,'canje'=>$payload], 201);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 422);
        } catch (QueryException $e) {
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 422);
        }
    }

    // GET /api/usos/{id}
    public function show(int $id)
    {
        return response()->json($this->service->obtenerCabeceraYDetalle($id));
    }

    // PUT /api/usos/{id}
    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'estado'      => ['required','in:COMPLETADO,PENDIENTE,ANULADO'],
            'comprobante' => ['nullable','string','max:200'],
        ]);

        $this->service->actualizar($id, $data);
        return response()->json(['ok'=>true]);
    }

    // DELETE /api/usos/{id}
    public function destroy(int $id)
    {
        try {
            $this->service->eliminar($id);
            return response()->json(['ok'=>true]);
        } catch (\RuntimeException $e) {
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 422);
        }
    }

    // GET /api/usos/saldo/{clienteId}
    public function saldoCliente(int $clienteId)
    {
        return response()->json([
            'cliente_id' => $clienteId,
            'saldo' => $this->service->saldoDisponible($clienteId),
        ]);
    }
}
