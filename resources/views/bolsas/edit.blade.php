@extends('layouts.app')
@section('title','Editar Bolsa')

@section('content')
<div class="px-6 py-6">
  <div class="bg-white rounded-xl shadow p-6 max-w-4xl">
    <h2 class="text-lg font-semibold mb-4">Editar Bolsa #{{ $row->id }}</h2>

    @if ($errors->any())
      <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded p-3">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="post" action="{{ route('bolsas.update',$row->id) }}" class="grid md:grid-cols-2 gap-4">
      @csrf @method('PUT')

      <div>
        <label class="block text-sm text-gray-600">Cliente *</label>
        <select name="cliente_id" class="w-full border rounded px-3 py-2" required>
          @foreach($clientes as $c)
            <option value="{{ $c->id }}" @selected(old('cliente_id',$row->cliente_id)==$c->id)>
              {{ $c->apellido }}, {{ $c->nombre }}
            </option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="block text-sm text-gray-600">Fecha Asignación *</label>
        <input type="datetime-local" name="fecha_asignacion"
               value="{{ old('fecha_asignacion', \Carbon\Carbon::parse($row->fecha_asignacion)->format('Y-m-d\TH:i')) }}"
               class="w-full border rounded px-3 py-2" required>
      </div>

      <div>
        <label class="block text-sm text-gray-600">Parámetro de Vencimiento</label>
        <select name="param_vencimiento_id" class="w-full border rounded px-3 py-2">
          <option value="">— Ninguno —</option>
          @foreach($params as $p)
            <option value="{{ $p->id }}" @selected(old('param_vencimiento_id',$row->param_vencimiento_id)==$p->id)>
              {{ $p->descripcion }} ({{ $p->dias_duracion }} días)
            </option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="block text-sm text-gray-600">Fecha Caducidad</label>
        <input type="date" name="fecha_caducidad"
               value="{{ old('fecha_caducidad', $row->fecha_caducidad ? \Carbon\Carbon::parse($row->fecha_caducidad)->format('Y-m-d') : '') }}"
               class="w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm text-gray-600">Monto Operación</label>
        <input type="number" step="0.01" name="monto_operacion" value="{{ old('monto_operacion',$row->monto_operacion) }}"
               class="w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm text-gray-600">Puntos Asignados *</label>
        <input type="number" min="0" name="puntaje_asignado" value="{{ old('puntaje_asignado',$row->puntaje_asignado) }}"
               class="w-full border rounded px-3 py-2" required>
      </div>

      <div>
        <label class="block text-sm text-gray-600">Puntos Utilizados *</label>
        <input type="number" min="0" name="puntaje_utilizado" value="{{ old('puntaje_utilizado',$row->puntaje_utilizado) }}"
               class="w-full border rounded px-3 py-2" required>
      </div>

      <div>
        <label class="block text-sm text-gray-600">Saldo Puntos *</label>
        <input type="number" min="0" name="saldo_puntos" value="{{ old('saldo_puntos',$row->saldo_puntos) }}"
               class="w-full border rounded px-3 py-2" required>
        <p class="text-xs text-slate-500 mt-1">Debe cumplir: Asignado = Utilizado + Saldo.</p>
      </div>

      <div>
        <label class="block text-sm text-gray-600">Origen</label>
        <input type="text" name="origen" value="{{ old('origen',$row->origen) }}" class="w-full border rounded px-3 py-2" maxlength="100">
      </div>

      <div class="md:col-span-2 flex gap-2 mt-2">
        <button class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Guardar cambios</button>
        <a href="{{ route('bolsas.index') }}" class="px-4 py-2 rounded border">Cancelar</a>
      </div>
    </form>
  </div>
</div>
@endsection
