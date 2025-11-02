@extends('layouts.app')
@section('title','Detalle de Período')

@section('content')
<div class="px-6 py-6">
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Detalle</h2>
    <dl class="grid md:grid-cols-2 gap-4">
      <div><dt class="text-xs text-gray-500">Descripción</dt><dd class="text-gray-800">{{ $p->descripcion ?: '—' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Estado</dt><dd class="text-gray-800">{{ $p->activo ? 'Activo' : 'Inactivo' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Inicio</dt><dd class="text-gray-800">{{ \Carbon\Carbon::parse($p->fecha_inicio_validez)->format('d/m/Y') }}</dd></div>
      <div><dt class="text-xs text-gray-500">Fin</dt><dd class="text-gray-800">{{ $p->fecha_fin_validez ? \Carbon\Carbon::parse($p->fecha_fin_validez)->format('d/m/Y') : '—' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Validez</dt><dd class="text-gray-800">{{ $p->dias_duracion }} días</dd></div>
      <div><dt class="text-xs text-gray-500">Puntos Afectados</dt><dd class="text-gray-800">{{ number_format($puntos) }} ({{ $bolsas }} bolsas)</dd></div>
    </dl>

    <div class="mt-6 flex gap-2">
      <a href="{{ route('vencimientos.edit', $p->id) }}" class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Editar</a>
      <a href="{{ route('vencimientos.index') }}" class="px-4 py-2 rounded border">Volver</a>
    </div>
  </div>
</div>
@endsection
