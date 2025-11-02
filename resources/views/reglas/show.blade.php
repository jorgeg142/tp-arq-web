@extends('layouts.app')
@section('title','Detalle de Regla')

@section('content')
<div class="px-6 py-6">
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Detalle</h2>
    <dl class="grid md:grid-cols-2 gap-4">
      <div><dt class="text-xs text-gray-500">Descripción</dt><dd class="text-gray-800">{{ $r->descripcion ?: '—' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Rango</dt><dd class="text-gray-800">{{ $r->rango }}</dd></div>
      <div><dt class="text-xs text-gray-500">Equivalencia</dt><dd class="text-gray-800">{{ $r->eq_text }}</dd></div>
      <div><dt class="text-xs text-gray-500">Prioridad</dt><dd class="text-gray-800">{{ $r->prioridad }}</dd></div>
      <div><dt class="text-xs text-gray-500">Estado</dt><dd class="text-gray-800">{{ $r->activo ? 'Activo' : 'Inactivo' }}</dd></div>
    </dl>

    <div class="mt-6 flex gap-2">
      <a href="{{ route('reglas.edit', $r->id) }}" class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Editar</a>
      <a href="{{ route('reglas.index') }}" class="px-4 py-2 rounded border">Volver</a>
    </div>
  </div>
</div>
@endsection
