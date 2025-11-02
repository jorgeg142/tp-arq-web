<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VencimientosController extends Controller
{
    public function index(Request $request)
    {
        $q       = trim($request->get('q', ''));
        $perPage = (int) $request->get('per_page', 10) ?: 10;
        $today   = Carbon::today()->toDateString();

        // KPIs
        $periodosActivos = (int) DB::table('param_vencimientos')->where('activo',1)->count();

        $puntosAfectados = (int) DB::table('bolsas_puntos')->sum('puntaje_asignado');

        $proximosAVencer = (int) DB::table('bolsas_puntos')
            ->whereNotNull('fecha_caducidad')
            ->where('saldo_puntos','>',0)
            ->whereBetween('fecha_caducidad', [$today, Carbon::today()->addDays(28)->toDateString()])
            ->count();

        // Listado con puntos afectados por periodo
        $periodos = DB::table('param_vencimientos as p')
            ->leftJoin('bolsas_puntos as b', 'b.param_vencimiento_id', '=', 'p.id')
            ->select(
                'p.id','p.fecha_inicio_validez','p.fecha_fin_validez',
                'p.dias_duracion','p.descripcion','p.activo',
                DB::raw('COALESCE(SUM(b.puntaje_asignado),0) as puntos_afectados')
            )
            ->when($q !== '', fn($sql)=>$sql->where('p.descripcion','like','%'.$q.'%'))
            ->groupBy('p.id','p.fecha_inicio_validez','p.fecha_fin_validez','p.dias_duracion','p.descripcion','p.activo')
            ->orderByDesc('p.fecha_inicio_validez')
            ->paginate($perPage)
            ->appends($request->query());

        return view('vencimientos.index', compact(
            'periodos','periodosActivos','puntosAfectados','proximosAVencer','q','perPage'
        ));
    }

    public function create()
    {
        return view('vencimientos.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::table('param_vencimientos')->insert([
            'fecha_inicio_validez' => $data['fecha_inicio_validez'],
            'fecha_fin_validez'    => $data['fecha_fin_validez'],
            'dias_duracion'        => $data['dias_duracion'],
            'descripcion'          => $data['descripcion'] ?? null,
            'activo'               => $data['activo'] ?? 0,
        ]);

        return redirect()->route('vencimientos.index')->with('ok','Período creado.');
    }

    public function show(int $id)
    {
        $p = DB::table('param_vencimientos')->where('id',$id)->first();
        abort_unless($p, 404);

        $puntos = (int) DB::table('bolsas_puntos')->where('param_vencimiento_id',$id)->sum('puntaje_asignado');
        $bolsas = DB::table('bolsas_puntos')->where('param_vencimiento_id',$id)->count();

        return view('vencimientos.show', compact('p','puntos','bolsas'));
    }

    public function edit(int $id)
    {
        $p = DB::table('param_vencimientos')->where('id',$id)->first();
        abort_unless($p, 404);
        return view('vencimientos.edit', compact('p'));
    }

    public function update(Request $request, int $id)
    {
        $exists = DB::table('param_vencimientos')->where('id',$id)->exists();
        abort_unless($exists, 404);

        $data = $this->validated($request);

        DB::table('param_vencimientos')->where('id',$id)->update([
            'fecha_inicio_validez' => $data['fecha_inicio_validez'],
            'fecha_fin_validez'    => $data['fecha_fin_validez'],
            'dias_duracion'        => $data['dias_duracion'],
            'descripcion'          => $data['descripcion'] ?? null,
            'activo'               => $data['activo'] ?? 0,
        ]);

        return redirect()->route('vencimientos.index')->with('ok','Período actualizado.');
    }

    public function destroy(int $id)
    {
        DB::table('param_vencimientos')->where('id',$id)->delete();
        return redirect()->route('vencimientos.index')->with('ok','Período eliminado.');
    }

    // --- Helper ---
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'fecha_inicio_validez' => ['required','date'],
            'fecha_fin_validez'    => ['nullable','date','after_or_equal:fecha_inicio_validez'],
            'dias_duracion'        => ['required','integer','min:0'],
            'descripcion'          => ['nullable','string','max:200'],
            'activo'               => ['nullable'], // checkbox
        ]);

        $data['activo'] = $request->boolean('activo') ? 1 : 0;

        // normalizar vacío -> null
        if (empty($data['fecha_fin_validez'])) $data['fecha_fin_validez'] = null;

        return $data;
    }
}
