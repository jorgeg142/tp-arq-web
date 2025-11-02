<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConceptosUsoController extends Controller
{
    public function index(Request $request)
    {
        $q       = trim($request->get('q', ''));
        $estado  = $request->get('estado');    // '1','0' o null
        $perPage = (int) $request->get('per_page', 10) ?: 10;

        $hasCategoria = Schema::hasColumn('conceptos_uso', 'categoria');

        $select = [
            'id',
            'descripcion_concepto',
            'puntos_requeridos',
            'activo',
        ];
        if ($hasCategoria) $select[] = 'categoria';

        $conceptos = DB::table('conceptos_uso')
            ->select($select)
            ->when($q !== '', function ($sql) use ($q) {
                $like = '%'.$q.'%';
                $sql->where('descripcion_concepto','like',$like);
            })
            ->when($estado !== null && $estado !== '', fn($sql) => $sql->where('activo',(int)$estado))
            ->orderBy('descripcion_concepto')
            ->paginate($perPage)
            ->appends($request->query());

        // KPIs
        $total       = (int) DB::table('conceptos_uso')->count();
        $activos     = (int) DB::table('conceptos_uso')->where('activo',1)->count();
        $inactivos   = $total - $activos;
        $byCategoria = $hasCategoria
            ? DB::table('conceptos_uso')
                ->select('categoria', DB::raw('COUNT(*) as cant'))
                ->groupBy('categoria')
                ->pluck('cant','categoria')
            : collect();

        return view('conceptos.index', compact(
            'conceptos','q','estado','perPage','total','activos','inactivos','byCategoria','hasCategoria'
        ));
    }

    public function create()
    {
        $hasCategoria = Schema::hasColumn('conceptos_uso', 'categoria');
        $categorias = $hasCategoria ? ['Descuento','Producto','Servicio','Consumo'] : [];
        return view('conceptos.create', compact('hasCategoria','categorias'));
    }

    public function store(Request $request)
    {
        $hasCategoria = Schema::hasColumn('conceptos_uso', 'categoria');

        $rules = [
            'descripcion_concepto' => ['required','string','max:200','unique:conceptos_uso,descripcion_concepto'],
            'puntos_requeridos'    => ['required','integer','min:0'],
            'activo'               => ['nullable'],
        ];
        if ($hasCategoria) {
            $rules['categoria'] = ['nullable','string','max:50'];
        }

        $data = $request->validate($rules);

        $row = [
            'descripcion_concepto' => $data['descripcion_concepto'],
            'puntos_requeridos'    => $data['puntos_requeridos'],
            'activo'               => isset($data['activo']) ? 1 : 0,
        ];
        if ($hasCategoria) $row['categoria'] = $data['categoria'] ?? null;

        DB::table('conceptos_uso')->insert($row);

        return redirect()->route('conceptos.index')->with('ok','Concepto creado.');
    }

    public function show(int $id)
    {
        $c = DB::table('conceptos_uso')->where('id',$id)->first();
        abort_unless($c, 404);
        $hasCategoria = property_exists($c,'categoria');
        return view('conceptos.show', compact('c','hasCategoria'));
    }

    public function edit(int $id)
    {
        $c = DB::table('conceptos_uso')->where('id',$id)->first();
        abort_unless($c, 404);
        $hasCategoria = \Illuminate\Support\Facades\Schema::hasColumn('conceptos_uso','categoria');
        $categorias = $hasCategoria ? ['Descuento','Producto','Servicio','Consumo'] : [];
        return view('conceptos.edit', compact('c','hasCategoria','categorias'));
    }

    public function update(Request $request, int $id)
    {
        $exists = DB::table('conceptos_uso')->where('id',$id)->exists();
        abort_unless($exists, 404);

        $hasCategoria = Schema::hasColumn('conceptos_uso', 'categoria');

        $rules = [
            'descripcion_concepto' => ['required','string','max:200','unique:conceptos_uso,descripcion_concepto,'.$id],
            'puntos_requeridos'    => ['required','integer','min:0'],
            'activo'               => ['nullable'],
        ];
        if ($hasCategoria) {
            $rules['categoria'] = ['nullable','string','max:50'];
        }

        $data = $request->validate($rules);

        $row = [
            'descripcion_concepto' => $data['descripcion_concepto'],
            'puntos_requeridos'    => $data['puntos_requeridos'],
            'activo'               => isset($data['activo']) ? 1 : 0,
        ];
        if ($hasCategoria) $row['categoria'] = $data['categoria'] ?? null;

        DB::table('conceptos_uso')->where('id',$id)->update($row);

        return redirect()->route('conceptos.index')->with('ok','Concepto actualizado.');
    }

    public function destroy(int $id)
    {
        DB::table('conceptos_uso')->where('id',$id)->delete();
        return redirect()->route('conceptos.index')->with('ok','Concepto eliminado.');
    }
}
