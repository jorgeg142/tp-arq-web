@extends('layouts.app')
@section('title','Detalle de Cliente')

@section('content')
<div class="px-6 py-6 space-y-4">
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Detalle</h2>
    <dl class="grid md:grid-cols-2 gap-4">
      <div><dt class="text-xs text-gray-500">Nombre</dt><dd class="text-gray-800">{{ $c->nombre }}</dd></div>
      <div><dt class="text-xs text-gray-500">Apellido</dt><dd class="text-gray-800">{{ $c->apellido }}</dd></div>
      <div><dt class="text-xs text-gray-500">Documento</dt><dd class="text-gray-800">{{ $c->numero_documento ?? '—' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Tipo Doc.</dt><dd class="text-gray-800">{{ $c->tipo_documento ?? '—' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Email</dt><dd class="text-gray-800">{{ $c->email ?? '—' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Teléfono</dt><dd class="text-gray-800">{{ $c->telefono ?? '—' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Nacimiento</dt><dd class="text-gray-800">{{ $c->fecha_nacimiento ?? '—' }}</dd></div>
      <div><dt class="text-xs text-gray-500">Estado</dt><dd class="text-gray-800">{{ $c->activo ? 'Activo' : 'Inactivo' }}</dd></div>
    </dl>

    <div class="mt-6 flex gap-2">
      <a href="{{ route('clientes.edit', $c->id) }}" class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Editar</a>
      <a href="{{ route('clientes.index') }}" class="px-4 py-2 rounded border">Volver</a>
    </div>
  </div>
</div>
@endsection
