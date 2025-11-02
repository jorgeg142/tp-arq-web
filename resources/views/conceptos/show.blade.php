@extends('layouts.app')
@section('title','Detalle de Concepto')

@section('content')
<div class="px-6 py-6 space-y-4">
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Detalle</h2>
    <dl class="grid md:grid-cols-2 gap-4">
      <div><dt class="text-xs text-gray-500">Descripción</dt><dd class="text-gray-800">{{ $c->descripcion_concepto }}</dd></div>
      <div><dt class="text-xs text-gray-500">Puntos Requeridos</dt><dd class="text-gray-800">{{ number_format($c->puntos_requeridos) }}</dd></div>
      @if($hasCategoria ?? false)
        <div><dt class="text-xs text-gray-500">Categoría</dt><dd class="text-gray-800">{{ $c->categoria ?: '—' }}</dd></div>
      @endif
      <div><dt class="text-xs text-gray-500">Estado</dt><dd class="text-gray-800">{{ $c->activo ? 'Activo' : 'Inactivo' }}</dd></div>
    </dl>

    <div class="mt-6 flex gap-2">
      <a href="{{ route('conceptos.edit', $c->id) }}" class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Editar</a>
      <a href="{{ route('conceptos.index') }}" class="px-4 py-2 rounded border">Volver</a>
    </div>
  </div>
</div>
@endsection
