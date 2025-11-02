@php
   $c 
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
</div>
