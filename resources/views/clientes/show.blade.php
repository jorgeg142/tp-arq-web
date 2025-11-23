@extends('layouts.app')
@section('title','Detalle de Cliente')

@section('content')
<div class="px-6 py-6 space-y-4">
  <div class="bg-white rounded-xl shadow p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
      <h2 class="text-lg font-semibold">Detalle</h2>
      <div class="flex flex-wrap gap-2 text-sm">
        <span class="px-3 py-1 rounded-full border border-blue-200 bg-blue-50 text-blue-700">{{ number_format($c->puntos) }} pts</span>
        <span class="px-3 py-1 rounded-full border border-amber-200 bg-amber-50 text-amber-700">{{ $c->nivel['nombre'] }}</span>
        @if($c->nivel['siguiente'])
          <span class="px-3 py-1 rounded-full border border-slate-200 text-slate-700">Faltan {{ number_format($c->nivel['siguiente']['faltan']) }} pts para {{ $c->nivel['siguiente']['nombre'] }}</span>
        @else
          <span class="px-3 py-1 rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700">Nivel máximo alcanzado</span>
        @endif
      </div>
    </div>

    <dl class="grid md:grid-cols-2 gap-4">
      <div><dt class="text-xs text-gray-500">Nombre</dt><dd class="text-gray-800">{{ $c->nombre }}</dd></div>
      <div><dt class="text-xs text-gray-500">Apellido</dt><dd class="text-gray-800">{{ $c->apellido }}</dd></div>
      <div><dt class="text-xs text-gray-500">Documento</dt><dd class="text-gray-800">{{ $c->numero_documento ?? '—' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Tipo Doc.</dt><dd class="text-gray-800">{{ $c->tipo_documento ?? '—' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Email</dt><dd class="text-gray-800">{{ $c->email ?? '—' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Teléfono</dt><dd class="text-gray-800">{{ $c->telefono ?? '—' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Nacimiento</dt><dd class="text-gray-800">{{ $c->fecha_nacimiento ?? '—' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Estado</dt><dd class="text-gray-800">{{ $c->activo ? 'Activo' : 'Inactivo' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Código de referido</dt><dd class="text-gray-800">{{ $c->codigo_referido }}</dd></div>
      <div>
        <dt class="text-xs text-gray-500">Referido por</dt>
        <dd class="text-gray-800">{{ $c->referido_por_nombre ? $c->referido_por_nombre.' ('.$c->referido_por_codigo.')' : '—' }}</dd>
      </div>
    </dl>

    <div class="mt-6">
      <h3 class="text-sm font-semibold text-slate-800 mb-2">Beneficios de {{ $c->nivel['nombre'] }}</h3>
      <ul class="list-disc list-inside text-sm text-slate-700 space-y-1">
        @foreach($c->nivel['beneficios'] as $beneficio)
          <li>{{ $beneficio }}</li>
        @endforeach
      </ul>
    </div>

    <div class="mt-6 flex gap-2">
      <a href="{{ route('clientes.edit', $c->id) }}" class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Editar</a>
      <a href="{{ route('clientes.referidos', $c->id) }}" class="bg-amber-600 text-white px-4 py-2 rounded hover:bg-amber-500">Código de referidos</a>
      <a href="{{ route('clientes.index') }}" class="px-4 py-2 rounded border">Volver</a>
    </div>
  </div>
</div>
@endsection
