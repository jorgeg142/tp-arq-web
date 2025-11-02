@extends('layouts.app')
@section('title','Editar Canje')

@section('content')
<div class="px-6 py-6">
  <div class="bg-white rounded-xl shadow p-6 max-w-3xl">
    <h2 class="text-lg font-semibold mb-1">Editar Canje #TXN-{{ str_pad($cab->id,3,'0',STR_PAD_LEFT) }}</h2>
    <p class="text-sm text-slate-500 mb-4">
      Cliente: <b>{{ $cab->nombre }} {{ $cab->apellido }}</b> — Concepto: <b>{{ $cab->concepto }}</b> — Puntos: <b>{{ $cab->puntaje_utilizado }}</b>
    </p>

    @if ($errors->any())
      <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded p-3">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="post" action="{{ route('usos.update',$cab->id) }}" class="space-y-4">
      @csrf @method('PUT')

      <div>
        <label class="block text-sm text-gray-600">Estado *</label>
        <select name="estado" class="w-full border rounded px-3 py-2" required>
          @foreach($estados as $k=>$v)
            <option value="{{ $k }}" @selected(old('estado',$cab->estado)===$k)>{{ $v }}</option>
          @endforeach
        </select>
        <p class="text-xs text-slate-500 mt-1">
          Cambiar a <b>ANULADO</b> no devuelve puntos; es solo para trazabilidad.
        </p>
      </div>

      <div>
        <label class="block text-sm text-gray-600">Comprobante</label>
        <input type="text" name="comprobante" value="{{ old('comprobante',$cab->comprobante) }}"
               class="w-full border rounded px-3 py-2" maxlength="200">
      </div>

      <div class="flex gap-2">
        <button class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Guardar</button>
        <a href="{{ route('usos.index') }}" class="px-4 py-2 rounded border">Cancelar</a>
      </div>
    </form>

    <hr class="my-6">

    <div class="text-xs text-slate-500">
      <p><b>Fecha:</b> {{ \Carbon\Carbon::parse($cab->fecha)->format('d/m/Y H:i') }}</p>
      <p><b>Cliente ID:</b> {{ $cab->cliente_id }}</p>
      <p><b>Concepto ID:</b> {{ $cab->concepto_uso_id ?? '—' }}</p>
    </div>
  </div>
</div>
@endsection
