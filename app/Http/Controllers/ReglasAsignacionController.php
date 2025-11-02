<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReglasAsignacionController extends Controller
{
    public function index(Request $request)
    {
        $rules = DB::table('reglas_asignacion')
            ->orderBy('prioridad')
            ->orderBy('limite_inferior')
            ->get();

        // Ratio relativo (x) tomando como base la equivalencia mínima
        $baseEq = max(1, (int) floor($rules->min('monto_equivalencia') ?: 1));
        $rules = $rules->map(function($r) use ($baseEq) {
            $factor = $baseEq > 0 ? round($baseEq / (float)$r->monto_equivalencia, 1) : 1;
            $r->ratio_x = $factor;                          // ej: 1.5x
            $r->rango   = $this->rangeLabel($r->limite_inferior, $r->limite_superior);
            $r->eq_text = '1 punto cada '.rtrim(rtrim(number_format($r->monto_equivalencia,2), '0'),'.');
            return $r;
        });

        return view('reglas.index', compact('rules'));
    }

    public function create()
    {
        return view('reglas.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // valida solapamiento si queda activa
        if (($data['activo'] ?? 0) == 1 && $this->overlaps(null, $data['limite_inferior'], $data['limite_superior'])) {
            return back()->withErrors(['limite_inferior' => 'El rango se solapa con otra regla activa.'])
                         ->withInput();
        }

        DB::table('reglas_asignacion')->insert([
            'limite_inferior'    => $data['limite_inferior'],
            'limite_superior'    => $data['limite_superior'],
            'monto_equivalencia' => $data['monto_equivalencia'],
            'descripcion'        => $data['descripcion'] ?? null,
            'prioridad'          => $data['prioridad'],
            'activo'             => $data['activo'] ?? 0,
        ]);

        return redirect()->route('reglas.index')->with('ok','Regla creada.');
    }

    public function show(int $id)
    {
        $r = DB::table('reglas_asignacion')->where('id',$id)->first();
        abort_unless($r, 404);
        $r->rango   = $this->rangeLabel($r->limite_inferior, $r->limite_superior);
        $r->eq_text = '1 punto cada '.rtrim(rtrim(number_format($r->monto_equivalencia,2), '0'),'.');
        return view('reglas.show', compact('r'));
    }

    public function edit(int $id)
    {
        $r = DB::table('reglas_asignacion')->where('id',$id)->first();
        abort_unless($r, 404);
        return view('reglas.edit', compact('r'));
    }

    public function update(Request $request, int $id)
    {
        $exists = DB::table('reglas_asignacion')->where('id',$id)->exists();
        abort_unless($exists, 404);

        $data = $this->validated($request);

        if (($data['activo'] ?? 0) == 1 && $this->overlaps($id, $data['limite_inferior'], $data['limite_superior'])) {
            return back()->withErrors(['limite_inferior' => 'El rango se solapa con otra regla activa.'])
                         ->withInput();
        }

        DB::table('reglas_asignacion')->where('id',$id)->update([
            'limite_inferior'    => $data['limite_inferior'],
            'limite_superior'    => $data['limite_superior'],
            'monto_equivalencia' => $data['monto_equivalencia'],
            'descripcion'        => $data['descripcion'] ?? null,
            'prioridad'          => $data['prioridad'],
            'activo'             => $data['activo'] ?? 0,
        ]);

        return redirect()->route('reglas.index')->with('ok','Regla actualizada.');
    }

    public function destroy(int $id)
    {
        DB::table('reglas_asignacion')->where('id',$id)->delete();
        return redirect()->route('reglas.index')->with('ok','Regla eliminada.');
    }

    // ------- Helpers -------

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'limite_inferior'    => ['required','numeric','min:0'],
            'limite_superior'    => ['nullable','numeric','gt:limite_inferior'],
            'monto_equivalencia' => ['required','numeric','gt:0'],
            'descripcion'        => ['nullable','string','max:200'],
            'prioridad'          => ['required','integer','min:1','max:100'],
            'activo'             => ['nullable'], // checkbox
        ]);

        // Normaliza checkbox
        $data['activo'] = $request->boolean('activo') ? 1 : 0;

        // Si viene vacío, usar NULL
        if ($data['limite_superior'] === null || $data['limite_superior'] === '') {
            $data['limite_superior'] = null; // ∞
        }

        return $data;
    }

    // ¿Se solapa con alguna regla activa distinta de $ignoreId?
    private function overlaps(?int $ignoreId, float $low, $highNullable): bool
    {
        $high = $highNullable; // puede ser null (∞)

        $q = DB::table('reglas_asignacion')->where('activo',1);
        if ($ignoreId) $q->where('id','<>',$ignoreId);

        return $q->where(function($w) use ($low, $high) {
                // (a,b] se solapa con (c,d] si a<d && c<b (tratando NULL como +∞)
                $w->where(function($x) use ($low, $high) {
                    $x->where('limite_inferior','<', $high ?? 9e18)
                      ->where(function($y) use ($low) {
                          $y->whereNull('limite_superior')
                            ->orWhere('limite_superior','>', $low);
                      });
                });
            })->exists();
    }

    private function rangeLabel($low, $high): string
    {
        $a = '$'.rtrim(rtrim(number_format($low,2), '0'),'.');
        if ($high === null) return $a.' - ∞';
        $b = '$'.rtrim(rtrim(number_format($high,2), '0'),'.');
        return $a.' - '.$b;
    }
}
