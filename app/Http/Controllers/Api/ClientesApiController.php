<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ClientesService;

class ClientesApiController extends Controller
{
    public function __construct(private ClientesService $svc) {}

    // GET /api/clientes?q=&estado=&per_page=10
    public function index(Request $request)
    {
        $q       = trim($request->query('q',''));
        $estado  = $request->query('estado');
        $perPage = (int) $request->query('per_page', 10) ?: 10;

        $rows = $this->svc->listar($q, $estado, $perPage);

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

    // GET /api/clientes/{id}
    public function show(int $id)
    {
        $row = $this->svc->obtener($id);
        if (!$row) return response()->json(['ok'=>false,'error'=>'Not Found'], 404);
        return response()->json(['ok'=>true,'row'=>$row]);
    }

    // POST /api/clientes
    public function store(Request $request)
    {
        if ($request->filled('codigo_referente')) {
            $request->merge(['codigo_referente' => trim($request->input('codigo_referente'))]);
        } else {
            $request->merge(['codigo_referente' => null]);
        }

        // mismas reglas que tu helper 'validated' (create)
        $data = $request->validate([
            'nombre'           => ['required','string','max:100'],
            'apellido'         => ['required','string','max:100'],
            'numero_documento' => ['nullable','string','max:50','unique:clientes,numero_documento'],
            'tipo_documento'   => ['nullable','string','max:30'],
            'nacionalidad'     => ['nullable','string','max:50'],
            'email'            => ['nullable','email','max:150','unique:clientes,email'],
            'telefono'         => ['nullable','string','max:50'],
            'fecha_nacimiento' => ['nullable','date'],
            'activo'           => ['nullable'],
            'codigo_referente' => ['nullable','string','max:16','exists:clientes,codigo_referido'],
        ]);
        $data['activo'] = $request->boolean('activo') ? 1 : 0;

        $id = $this->svc->crear($data, $data['codigo_referente'] ?? null);
        return response()->json(['ok'=>true,'id'=>$id,'row'=>$this->svc->obtener($id)], 201);
    }

    // PUT /api/clientes/{id}
    public function update(Request $request, int $id)
    {
        if (!$this->svc->obtener($id)) {
            return response()->json(['ok'=>false,'error'=>'Not Found'], 404);
        }

        // mismas reglas que tu helper 'validated' (update)
        $data = $request->validate([
            'nombre'           => ['required','string','max:100'],
            'apellido'         => ['required','string','max:100'],
            'numero_documento' => ['nullable','string','max:50','unique:clientes,numero_documento,'.$id],
            'tipo_documento'   => ['nullable','string','max:30'],
            'nacionalidad'     => ['nullable','string','max:50'],
            'email'            => ['nullable','email','max:150','unique:clientes,email,'.$id],
            'telefono'         => ['nullable','string','max:50'],
            'fecha_nacimiento' => ['nullable','date'],
            'activo'           => ['nullable'],
        ]);
        $data['activo'] = $request->boolean('activo') ? 1 : 0;

        $this->svc->actualizar($id, $data);
        return response()->json(['ok'=>true,'row'=>$this->svc->obtener($id)]);
    }

    // DELETE /api/clientes/{id}
    public function destroy(int $id)
    {
        if (!$this->svc->obtener($id)) {
            return response()->json(['ok'=>false,'error'=>'Not Found'], 404);
        }
        $this->svc->eliminar($id);
        return response()->json(['ok'=>true]);
    }
}
