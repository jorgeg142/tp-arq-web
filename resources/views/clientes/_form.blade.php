@php
   // $c (edición) puede venir o no (creación)
@endphp

<div class="grid md:grid-cols-2 gap-4">
  <div>
    <label class="block text-sm text-gray-600">Nombre *</label>
    <input name="nombre" type="text" required
           value="{{ old('nombre', $c->nombre ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('nombre') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Apellido *</label>
    <input name="apellido" type="text" required
           value="{{ old('apellido', $c->apellido ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('apellido') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Nº Documento</label>
    <input name="numero_documento" type="text"
           value="{{ old('numero_documento', $c->numero_documento ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('numero_documento') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Tipo Documento</label>
    <input name="tipo_documento" type="text"
           value="{{ old('tipo_documento', $c->tipo_documento ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('tipo_documento') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Nacionalidad</label>
    <input name="nacionalidad" type="text"
           value="{{ old('nacionalidad', $c->nacionalidad ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('nacionalidad') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Email</label>
    <input name="email" type="email"
           value="{{ old('email', $c->email ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Teléfono</label>
    <input name="telefono" type="text"
           value="{{ old('telefono', $c->telefono ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('telefono') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Fecha de Nacimiento</label>
    <input name="fecha_nacimiento" type="date"
           value="{{ old('fecha_nacimiento', isset($c->fecha_nacimiento) ? \Carbon\Carbon::parse($c->fecha_nacimiento)->format('Y-m-d') : '') }}"
           class="w-full border rounded px-3 py-2">
    @error('fecha_nacimiento') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div class="md:col-span-2">
    <label class="inline-flex items-center gap-2">
      <input name="activo" type="checkbox" value="1" class="rounded"
             {{ old('activo', $c->activo ?? 1) ? 'checked' : '' }}>
      <span class="text-sm text-gray-700">Activo</span>
    </label>
  </div>

  @if(!isset($c))
    <div class="md:col-span-2">
      <label class="block text-sm text-gray-600">Código de referido (opcional)</label>
      <input name="codigo_referente" type="text"
             value="{{ old('codigo_referente') }}"
             class="w-full border rounded px-3 py-2" placeholder="Ej: ABCD1234">
      <p class="text-xs text-gray-500 mt-1">Si alguien te invitó, ingresá su código para que ambos reciban puntos.</p>
      @error('codigo_referente') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
  @else
    <div class="md:col-span-2 grid md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm text-gray-600">Tu código de referido</label>
        <input type="text" readonly value="{{ $c->codigo_referido }}"
               class="w-full border rounded px-3 py-2 bg-slate-50 text-gray-700">
        <p class="text-xs text-gray-500 mt-1">Compartilo para que otros se registren y ganen puntos.</p>
      </div>
      <div>
        <label class="block text-sm text-gray-600">Referido por</label>
        @if(!empty($c->referido_por_nombre))
          <div class="w-full border rounded px-3 py-2 bg-slate-50 text-gray-700 flex justify-between items-center">
            <span>{{ $c->referido_por_nombre }}</span>
            <span class="text-xs text-slate-500">({{ $c->referido_por_codigo }})</span>
          </div>
        @else
          <input type="text" readonly value="—" class="w-full border rounded px-3 py-2 bg-slate-50 text-gray-500">
        @endif
      </div>
    </div>
  @endif
</div>
