<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

class UsosController extends Controller
{
    public function index(Request $request)
    {
        $q       = trim($request->get('q',''));
        $estado  = $request->get('estado'); // COMPLETADO|PENDIENTE|null
        $perPage = (int) $request->get('per_page', 10) ?: 10;
        $today   = Carbon::today()->toDateString();

        // KPIs
        $canjesHoy = (int) DB::table('uso_puntos_cab')
            ->whereDate('fecha', $today)->count();

        $totalCanjeados = (int) DB::table('uso_puntos_cab')->sum('puntaje_utilizado');

        $pendientes = (int) DB::table('uso_puntos_cab')->where('estado','PENDIENTE')->count();

        $promedio = (int) (DB::table('uso_puntos_cab')->avg('puntaje_utilizado') ?: 0);

        // Listado
        $rows = DB::table('uso_puntos_cab as u')
            ->join('clientes as c','c.id','=','u.cliente_id')
            ->leftJoin('conceptos_uso as cu','cu.id','=','u.concepto_uso_id')
            ->select(
                'u.id','u.puntaje_utilizado','u.fecha','u.estado','u.comprobante',
                'c.id as cliente_id','c.nombre','c.apellido',
                DB::raw("COALESCE(cu.descripcion_concepto, CONCAT('Concepto #',u.concepto_uso_id)) as concepto")
            )
            ->when($q !== '', function($sql) use ($q){
                $like = '%'.$q.'%';
                $sql->where(function($w) use ($like){
                    $w->where('c.nombre','like',$like)
                      ->orWhere('c.apellido','like',$like)
                      ->orWhere('u.comprobante','like',$like)
                      ->orWhere('cu.descripcion_concepto','like',$like);
                });
            })
            ->when($estado, fn($sql)=>$sql->where('u.estado',$estado))
            ->orderByDesc('u.fecha')
            ->paginate($perPage)
            ->appends($request->query());

        return view('usos.index', compact(
            'rows','q','estado','perPage','canjesHoy','totalCanjeados','pendientes','promedio'
        ));
    }

    public function create()
    {
        $clientes  = DB::table('clientes')->where('activo',1)->orderBy('apellido')->get();
        $conceptos = DB::table('conceptos_uso')->where('activo',1)->orderBy('descripcion_concepto')->get();
        return view('usos.create', compact('clientes','conceptos'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id'     => ['required','integer','exists:clientes,id'],
            'modo'           => ['required','in:concepto,libre'], // cómo descontar
            'concepto_uso_id'=> ['nullable','integer','exists:conceptos_uso,id'],
            'puntos'         => ['nullable','integer','min:1'],
            'comprobante'    => ['nullable','string','max:200'],
        ]);

        // Resolver puntos a usar
        if ($data['modo'] === 'concepto') {
            if (!$data['concepto_uso_id']) {
                return back()->withErrors(['concepto_uso_id'=>'Seleccioná un concepto'])->withInput();
            }
            $req = DB::table('conceptos_uso')->where('id',$data['concepto_uso_id'])->value('puntos_requeridos');
            if (!$req) return back()->withErrors(['concepto_uso_id'=>'Concepto no válido'])->withInput();
            $puntos = (int)$req;
        } else {
            if (!$data['puntos']) {
                return back()->withErrors(['puntos'=>'Ingresá los puntos a usar'])->withInput();
            }
            $puntos = (int)$data['puntos'];
        }

        // Verificar disponibilidad (opcional, SP igual valida)
        $saldo = (int) DB::table('bolsas_puntos')
            ->where('cliente_id',$data['cliente_id'])
            ->where('saldo_puntos','>',0)
            ->where(function($w){
                $w->whereNull('fecha_caducidad')->orWhere('fecha_caducidad','>=', now()->toDateString());
            })->sum('saldo_puntos');

        if ($saldo < $puntos) {
            return back()->withErrors(['puntos'=>"Puntos insuficientes. Saldo disponible: {$saldo}."])
                         ->withInput();
        }

        // Ejecutar SP FIFO
        try {
            DB::statement('CALL sp_usar_puntos_fifo(?,?,?)', [
                $data['cliente_id'],
                $puntos,
                $data['modo']==='concepto' ? $data['concepto_uso_id'] : 0
            ]);

            // opcional: actualizar comprobante si llegó
            if (!empty($data['comprobante'])) {
                DB::table('uso_puntos_cab')
                  ->where('cliente_id',$data['cliente_id'])
                  ->orderByDesc('id')->limit(1)
                  ->update(['comprobante'=>$data['comprobante']]);
            }

        } catch (QueryException $e) {
            // Captura SIGNAL del SP
            return back()->withErrors(['db'=>$e->getMessage()])->withInput();
        }

        return redirect()->route('usos.index')->with('ok','Canje procesado correctamente.');
    }

    // Detalle para el modal 
    public function show(int $id)
    {
        $cab = DB::table('uso_puntos_cab as u')
            ->join('clientes as c','c.id','=','u.cliente_id')
            ->leftJoin('conceptos_uso as cu','cu.id','=','u.concepto_uso_id')
            ->select(
                'u.*',
                'c.nombre','c.apellido','c.numero_documento',
                DB::raw("COALESCE(cu.descripcion_concepto, CONCAT('Concepto #',u.concepto_uso_id)) as concepto")
            )->where('u.id',$id)->first();
        abort_unless($cab, 404);

        $det = DB::table('uso_puntos_det as d')
            ->join('bolsas_puntos as b','b.id','=','d.bolsa_id')
            ->select('d.puntaje_utilizado','d.fecha_detalle','b.fecha_asignacion','b.fecha_caducidad','b.origen')
            ->where('d.cabecera_id',$id)
            ->orderBy('d.id')->get();

        return response()->json(['cabecera'=>$cab,'detalles'=>$det]);
    }

    public function edit(int $id)
    {
        $cab = DB::table('uso_puntos_cab as u')
            ->join('clientes as c','c.id','=','u.cliente_id')
            ->leftJoin('conceptos_uso as cu','cu.id','=','u.concepto_uso_id')
            ->select(
                'u.*',
                'c.nombre','c.apellido',
                DB::raw("COALESCE(cu.descripcion_concepto, CONCAT('Concepto #',u.concepto_uso_id)) as concepto")
            )->where('u.id',$id)->first();

        abort_unless($cab, 404);

        // Estados permitidos (no devolvemos puntos: es solo tracking)
        $estados = ['COMPLETADO' => 'Completado', 'PENDIENTE' => 'Pendiente', 'ANULADO' => 'Anulado'];
        return view('usos.edit', compact('cab','estados'));
    }

    public function update(Request $request, int $id)
    {
        $cab = DB::table('uso_puntos_cab')->where('id',$id)->first();
        abort_unless($cab, 404);

        $data = $request->validate([
            'estado'      => ['required','in:COMPLETADO,PENDIENTE,ANULADO'],
            'comprobante' => ['nullable','string','max:200'],
        ]);

        DB::table('uso_puntos_cab')->where('id',$id)->update([
            'estado'      => $data['estado'],
            'comprobante' => $data['comprobante'] ?? null,
        ]);

        return redirect()->route('usos.index')->with('ok','Canje actualizado.');
    }

    public function destroy(int $id)
    {
        // Si tiene detalles, no permitimos borrar (evita desbalance)
        $det = DB::table('uso_puntos_det')->where('cabecera_id',$id)->count();
        if ($det > 0) {
            return back()->withErrors([
                'del' => "No se puede eliminar el canje porque ya impactó en bolsas (tiene {$det} detalle(s)). " .
                        "Si necesitás revertir, implementemos una anulación con reversa."
            ]);
        }

        DB::table('uso_puntos_cab')->where('id',$id)->delete();
        return back()->with('ok','Canje eliminado.');
    }
}
