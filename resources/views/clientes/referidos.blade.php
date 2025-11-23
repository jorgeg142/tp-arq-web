@extends('layouts.app')
@section('title','Código de referidos')

@section('content')
<div class="px-6 py-6 space-y-4">
  <div class="bg-white rounded-xl shadow p-6 space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div>
        <p class="text-sm text-slate-500">Cliente</p>
        <h2 class="text-xl font-semibold text-slate-800">{{ $c->nombre }} {{ $c->apellido }}</h2>
      </div>
      <div class="flex gap-2 text-sm">
        <a href="{{ route('clientes.show', $c->id) }}" class="px-3 py-2 rounded-md border border-slate-200 hover:bg-slate-50">Volver al detalle</a>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 border-b pb-2 text-sm">
      <a href="{{ route('clientes.show', $c->id) }}" class="px-3 py-1.5 rounded-md {{ request()->routeIs('clientes.show') ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-700 hover:bg-slate-50' }}">Detalle</a>
      <span class="px-3 py-1.5 rounded-md bg-slate-900 text-white">Código de referidos</span>
    </div>

    @if(session('ok'))
      <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-md text-sm">
        {{ session('ok') }}
      </div>
    @endif

    <form method="POST" action="{{ route('clientes.referidos.update', $c->id) }}" class="space-y-6">
      @csrf
      @method('PUT')

      <div class="grid md:grid-cols-2 gap-6">
        <div class="space-y-3">
          <div>
            <label class="block text-sm text-gray-600">Código de referido</label>
            <input name="codigo_referido" type="text" maxlength="16"
                   value="{{ old('codigo_referido', $c->codigo_referido) }}"
                   class="w-full border rounded px-3 py-2" placeholder="Dejar vacío para generar uno nuevo">
            <p class="text-xs text-gray-500 mt-1">El código debe ser único. Si lo dejás vacío, se generará automáticamente.</p>
            @error('codigo_referido') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
          </div>

          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm text-gray-600">Puntos para quien refiere</label>
              <input name="puntos_por_referir" type="number" min="0"
                     value="{{ old('puntos_por_referir', $c->puntos_por_referir) }}"
                     class="w-full border rounded px-3 py-2" placeholder="{{ $defaults['referidor'] }}">
              <p class="text-xs text-gray-500 mt-1">Se acreditan al dueño del código. Si lo dejás vacío se usarán {{ $defaults['referidor'] }} pts.</p>
              @error('puntos_por_referir') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
              <label class="block text-sm text-gray-600">Puntos para el nuevo cliente</label>
              <input name="puntos_bienvenida" type="number" min="0"
                     value="{{ old('puntos_bienvenida', $c->puntos_bienvenida) }}"
                     class="w-full border rounded px-3 py-2" placeholder="{{ $defaults['nuevo'] }}">
              <p class="text-xs text-gray-500 mt-1">Se acreditan al registrarse con el código. Si lo dejás vacío se usarán {{ $defaults['nuevo'] }} pts.</p>
              @error('puntos_bienvenida') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
          </div>

          <div class="flex gap-3">
            <button type="submit" class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Guardar</button>
            <a href="{{ route('clientes.index') }}" class="px-4 py-2 rounded border">Cancelar</a>
          </div>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 space-y-3">
          <h3 class="text-sm font-semibold text-slate-800">Vista previa</h3>
          <p class="text-sm text-slate-700">Comparte este código con nuevos clientes para otorgar los beneficios configurados.</p>
          <div class="text-center py-6 bg-white border border-dashed border-slate-300 rounded-md">
            <p class="text-xs text-slate-500">Código</p>
            <p class="text-2xl font-semibold tracking-widest text-slate-900">{{ old('codigo_referido', $c->codigo_referido) ?: '—' }}</p>
          </div>
          <ul class="text-sm text-slate-700 space-y-1">
            <li>🎁 Bono referidor: <strong>{{ old('puntos_por_referir', $c->puntos_por_referir ?? $defaults['referidor']) }}</strong> pts</li>
            <li>✨ Bono nuevo cliente: <strong>{{ old('puntos_bienvenida', $c->puntos_bienvenida ?? $defaults['nuevo']) }}</strong> pts</li>
          </ul>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
