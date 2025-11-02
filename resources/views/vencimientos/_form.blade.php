@php $p = $p ?? null; @endphp

<div class="grid md:grid-cols-2 gap-4">
  <div>
    <label class="block text-sm text-gray-600">Fecha Inicio *</label>
    <input type="date" name="fecha_inicio_validez" required
           value="{{ old('fecha_inicio_validez', isset($p)?\Carbon\Carbon::parse($p->fecha_inicio_validez)->format('Y-m-d'):'') }}"
           class="w-full border rounded px-3 py-2">
    @error('fecha_inicio_validez') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Fecha Fin (opcional)</label>
    <input type="date" name="fecha_fin_validez"
           value="{{ old('fecha_fin_validez', isset($p->fecha_fin_validez)?\Carbon\Carbon::parse($p->fecha_fin_validez)->format('Y-m-d'):'') }}"
           class="w-full border rounded px-3 py-2">
    @error('fecha_fin_validez') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Días de Validez *</label>
    <input type="number" name="dias_duracion" min="0" required
           value="{{ old('dias_duracion', $p->dias_duracion ?? 0) }}"
           class="w-full border rounded px-3 py-2">
    @error('dias_duracion') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm text-gray-600">Estado</label>
    <label class="inline-flex items-center gap-2 mt-2">
      <input type="checkbox" name="activo" value="1" class="rounded"
             {{ old('activo', $p->activo ?? 1) ? 'checked' : '' }}>
      <span class="text-sm text-gray-700">Activo</span>
    </label>
  </div>

  <div class="md:col-span-2">
    <label class="block text-sm text-gray-600">Descripción</label>
    <input type="text" name="descripcion"
           value="{{ old('descripcion', $p->descripcion ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('descripcion') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
  </div>
</div>
