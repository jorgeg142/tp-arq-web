@php $r = $r ?? null; @endphp

<div class="grid md:grid-cols-2 gap-4">
  <div class="md:col-span-2">
    <label class="block text-sm text-gray-600">Descripción (opcional)</label>
    <input type="text" name="descripcion" value="{{ old('descripcion', $r->descripcion ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('descripcion') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Límite Inferior *</label>
    <input type="number" step="0.01" min="0" name="limite_inferior" required
           value="{{ old('limite_inferior', $r->limite_inferior ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('limite_inferior') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Límite Superior (vacío = ∞)</label>
    <input type="number" step="0.01" name="limite_superior"
           value="{{ old('limite_superior', $r->limite_superior ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('limite_superior') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Equivalencia (monto por 1 punto) *</label>
    <input type="number" step="0.01" min="0.01" name="monto_equivalencia" required
           value="{{ old('monto_equivalencia', $r->monto_equivalencia ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('monto_equivalencia') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Prioridad *</label>
    <input type="number" name="prioridad" min="1" max="100" required
           value="{{ old('prioridad', $r->prioridad ?? 10) }}"
           class="w-full border rounded px-3 py-2">
    @error('prioridad') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div class="md:col-span-2">
    <label class="inline-flex items-center gap-2">
      <input type="checkbox" name="activo" value="1" class="rounded"
             {{ old('activo', $r->activo ?? 1) ? 'checked' : '' }}>
      <span class="text-sm text-gray-700">Activo</span>
    </label>
  </div>
</div>
