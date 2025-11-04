<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\BolsasService;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BolsasController extends Controller
{
    public function __construct(private BolsasService $svc) {}

    public function index(Request $request)
    {
        $q        = trim($request->get('q', ''));
        $estado   = $request->get('estado');   // null|activo|vencido|agotado
        $cliente  = $request->get('cliente');  // id de cliente opcional
        $perPage  = (int) $request->get('per_page', 10) ?: 10;

        // KPIs
        $kpis = $this->svc->kpis();

        // Serie últimos 6 meses
        $trend = $this->svc->trend(6);

        // Top clientes
        $topClientes = $this->svc->topClientes(5);

        // Listado con filtros
        $rows = $this->svc->listar($q, $estado, $cliente, $perPage)->appends($request->query());

        // clientes para combo (se mantiene directo)
        $clientes = DB::table('clientes')->select('id','nombre','apellido')->orderBy('apellido')->get();

        return view('bolsas.index', [
            'rows'      => $rows,
            'clientes'  => $clientes,
            'cliente'   => $cliente,
            'estado'    => $estado,
            'q'         => $q,
            'perPage'   => $perPage,
            // KPIs
            'kpiActivos'       => $kpis['kpiActivos'],
            'kpiUtilizados'    => $kpis['kpiUtilizados'],
            'kpiPorVencer'     => $kpis['kpiPorVencer'],
            'kpiBolsasActivas' => $kpis['kpiBolsasActivas'],
            // Trend
            'labels' => $trend['labels'],
            'asig'   => $trend['asig'],
            'used'   => $trend['used'],
            // Top
            'topClientes' => $topClientes,
        ]);
    }

    public function show(int $id)
    {
        $res = $this->svc->obtener($id);
        abort_unless($res, 404);
        return response()->json($res);
    }

    public function export(Request $request)
    {
        $q       = trim($request->get('q', ''));
        $estado  = $request->get('estado');
        $cliente = $request->get('cliente');

        $ss = $this->svc->buildExport($q, $estado, $cliente);
        $writer = new Xlsx($ss);
        $filename = 'bolsa_puntos_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function() use ($writer){
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function create()
    {
        $clientes  = DB::table('clientes')->where('activo',1)->orderBy('apellido')->get();
        $params    = DB::table('param_vencimientos')->where('activo',1)->orderByDesc('fecha_inicio_validez')->get();
        return view('bolsas.create', compact('clientes','params'));
    }

    public function store(Request $request)
    {
       $v = $request->validate([
            'cliente_id'           => ['required','integer','exists:clientes,id'],
            'fecha_asignacion'     => ['required','date'],
            'param_vencimiento_id' => ['nullable','integer','exists:param_vencimientos,id'],
            'fecha_caducidad'      => ['nullable','date'],
            'puntaje_asignado'     => ['nullable','integer','min:0'], 
            'monto_operacion'      => ['required','numeric','min:0'],   // ← requerido para calcular
            'origen'               => ['nullable','string','max:100'],
        ]);

        $this->svc->crear($v);
        return redirect()->route('bolsas.index')->with('ok','Bolsa creada correctamente.');
    }

    public function edit(int $id)
    {
        $row = DB::table('bolsas_puntos')->where('id',$id)->first();
        abort_unless($row, 404);
        $clientes = DB::table('clientes')->orderBy('apellido')->get();
        $params   = DB::table('param_vencimientos')->orderByDesc('fecha_inicio_validez')->get();
        return view('bolsas.edit', compact('row','clientes','params'));
    }

    public function update(Request $request, int $id)
    {
        $exists = DB::table('bolsas_puntos')->where('id',$id)->exists();
        abort_unless($exists, 404);

        $v = $request->validate([
            'cliente_id'           => ['required','integer','exists:clientes,id'],
            'fecha_asignacion'     => ['required','date'],
            'param_vencimiento_id' => ['nullable','integer','exists:param_vencimientos,id'],
            'fecha_caducidad'      => ['nullable','date'],
            'monto_operacion'      => ['required','numeric','min:0'],   // ← requerido para calcular
            'origen'               => ['nullable','string','max:100'],
        ]);

        $this->svc->actualizar($id, $v);
        return redirect()->route('bolsas.index')->with('ok','Bolsa actualizada.');
    }

    public function destroy(Request $request, int $id)
    {
        $usos = DB::table('uso_puntos_det')->where('bolsa_id',$id)->count();

        if ($usos > 0) {
            return redirect()
                ->route('bolsas.index') // <- no back(), mandalo a la lista
                ->withErrors(['del'=>"No se puede eliminar: la bolsa tiene usos asociados ({$usos})."]);
        }

        $this->svc->eliminar($id);

        return redirect()
            ->route('bolsas.index') // <- así la vista puede leer el flash
            ->with('ok','Bolsa eliminada.');
    }

}
