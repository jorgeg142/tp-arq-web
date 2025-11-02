@php
  // $c (edición) puede venir o no (creación)
@endphp

<div class="grid md:grid-cols-2 gap-4">
  <div class="md:col-span-2">
    <label class="block text-sm text-gray-600">Descripción del Concepto *</label>
    <input name="descripcion_concepto" type="text" required
           value="{{ old('descripcion_concepto', $c->descripcion_concepto ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('descripcion_concepto') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Puntos Requeridos *</label>
    <input name="puntos_requeridos" type="number" min="0" required
           value="{{ old('puntos_requeridos', $c->puntos_requeridos ?? 0) }}"
           class="w-full border rounded px-3 py-2">
    @error('puntos_requeridos') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  @isset($hasCategoria)
    @if($hasCategoria)
      <div>
        <label class="block text-sm text-gray-600">Categoría</label>
        <select name="categoria" class="w-full border rounded px-3 py-2">
          <option value="">—</option>
          @foreach($categorias as $cat)
            <option value="{{ $cat }}" @selected(old('categoria', $c->categoria ?? '')==$cat)>{{ $cat }}</option>
          @endforeach
        </select>
      </div>
    @endif
  @endisset

  <div class="md:col-span-2">
    <label class="inline-flex items-center gap-2">
      <input name="activo" type="checkbox" value="1" class="rounded"
             {{ old('activo', $c->activo ?? 1) ? 'checked' : '' }}>
      <span class="text-sm text-gray-700">Activo</span>
    </label>
  </div>
</div>
