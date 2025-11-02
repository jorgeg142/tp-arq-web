<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\BolsasService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BolsasApiController extends Controller
{
    public function __construct(private BolsasService $svc) {}

    // GET /api/bolsas?q=&estado=&cliente=&per_page=10
    public function index(Request $request)
    {
        $q       = trim($request->query('q',''));
        $estado  = $request->query('estado');          // activo|vencido|agotado|null
        $cliente = $request->query('cliente');         // id cliente
        $perPage = (int) $request->query('per_page', 10) ?: 10;

        $rows = $this->svc->listar($q, $estado, $cliente, $perPage);

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
        ]);
    }

    // GET /api/bolsas/kpis
    public function kpis()
    {
        return response()->json(['ok'=>true] + $this->svc->kpis());
    }

    // GET /api/bolsas/trend?months=6
    public function trend(Request $request)
    {
        $months = max(1, (int) $request->query('months', 6));
        return response()->json(['ok'=>true] + $this->svc->trend($months));
    }

    // GET /api/bolsas/top?limit=5
    public function top(Request $request)
    {
        $limit = max(1, (int) $request->query('limit', 5));
        return response()->json(['ok'=>true, 'rows'=>$this->svc->topClientes($limit)]);
    }

    // GET /api/bolsas/{id}
    public function show(int $id)
    {
        $res = $this->svc->obtener($id);
        if (!$res) return response()->json(['ok'=>false,'error'=>'Not Found'], 404);
        return response()->json(['ok'=>true] + $res);
    }

    // POST /api/bolsas
    public function store(Request $request)
    {
        $v = $request->validate([
            'cliente_id'           => ['required','integer','exists:clientes,id'],
            'fecha_asignacion'     => ['required','date'],
            'param_vencimiento_id' => ['nullable','integer','exists:param_vencimientos,id'],
            'fecha_caducidad'      => ['nullable','date'],
            'puntaje_asignado'     => ['required','integer','min:0'],
            'puntaje_utilizado'    => ['nullable','integer','min:0'],
            'saldo_puntos'         => ['nullable','integer','min:0'],
            'monto_operacion'      => ['nullable','numeric','min:0'],
            'origen'               => ['nullable','string','max:100'],
            'auto_por_monto'       => ['nullable','boolean'],
        ]);

        $id = $this->svc->crear($v);
        return response()->json(['ok'=>true,'id'=>$id] + $this->svc->obtener($id), 201);
    }

    // PUT /api/bolsas/{id}
    public function update(Request $request, int $id)
    {
        $exists = $this->svc->obtener($id);
        if (!$exists) return response()->json(['ok'=>false,'error'=>'Not Found'], 404);

        $v = $request->validate([
            'cliente_id'           => ['required','integer','exists:clientes,id'],
            'fecha_asignacion'     => ['required','date'],
            'param_vencimiento_id' => ['nullable','integer','exists:param_vencimientos,id'],
            'fecha_caducidad'      => ['nullable','date'],
            'puntaje_asignado'     => ['required','integer','min:0'],
            'puntaje_utilizado'    => ['required','integer','min:0'],
            'saldo_puntos'         => ['required','integer','min:0'],
            'monto_operacion'      => ['nullable','numeric','min:0'],
            'origen'               => ['nullable','string','max:100'],
        ]);

        // Regla: asignado = utilizado + saldo (mismo criterio de tu web)
        if ($v['puntaje_asignado'] !== ($v['puntaje_utilizado'] + $v['saldo_puntos'])) {
            return response()->json(['ok'=>false,'error'=>'Asignado debe ser igual a Usado + Saldo.'], 422);
        }

        $this->svc->actualizar($id, $v);
        return response()->json(['ok'=>true] + $this->svc->obtener($id));
    }

    // DELETE /api/bolsas/{id}
    public function destroy(int $id)
    {
        $exists = $this->svc->obtener($id);
        if (!$exists) return response()->json(['ok'=>false,'error'=>'Not Found'], 404);

        // misma salvaguarda que tu web: no borrar si tiene usos
        $usos = \DB::table('uso_puntos_det')->where('bolsa_id',$id)->count();
        if ($usos > 0) {
            return response()->json(['ok'=>false,'error'=>"No se puede eliminar: la bolsa tiene usos asociados ({$usos})."], 422);
        }
        $this->svc->eliminar($id);
        return response()->json(['ok'=>true]);
    }

    // GET /api/bolsas/export (descarga XLSX)
    public function export(Request $request)
    {
        $q       = trim($request->query('q',''));
        $estado  = $request->query('estado');
        $cliente = $request->query('cliente');

        $ss = $this->svc->buildExport($q, $estado, $cliente);
        $writer = new Xlsx($ss);
        $filename = 'bolsa_puntos_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function() use ($writer){
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
