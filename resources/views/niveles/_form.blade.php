<form method="post" action="{{ $action }}" class="space-y-6">
  @csrf
  @if(!empty($method))
    @method($method)
  @endif

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium text-slate-700">Nombre</label>
      <input type="text" name="nombre" value="{{ old('nombre', $nivel->nombre ?? '') }}" required
             class="mt-1 w-full rounded-lg border border-slate-300 bg-white
                    focus:border-slate-500 focus:ring-slate-500" />
      @error('nombre')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="block text-sm font-medium text-slate-700">Slug (opcional)</label>
      <input type="text" name="slug" value="{{ old('slug', $nivel->slug ?? '') }}"
             class="mt-1 w-full rounded-lg border border-slate-300 bg-white
                    focus:border-slate-500 focus:ring-slate-500" />
      @error('slug')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
  </div>

  <div>
    <label class="block text-sm font-medium text-slate-700">Descripción</label>
    <input type="text" name="descripcion" value="{{ old('descripcion', $nivel->descripcion ?? '') }}"
           class="mt-1 w-full rounded-lg border border-slate-300 bg-white
                  focus:border-slate-500 focus:ring-slate-500" />
    @error('descripcion')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium text-slate-700">Puntos mínimos</label>
      <input type="number" name="min_puntos" min="0"
             value="{{ old('min_puntos', $nivel->min_puntos ?? '') }}" required
             class="mt-1 w-full rounded-lg border border-slate-300 bg-white
                    focus:border-slate-500 focus:ring-slate-500" />
      @error('min_puntos')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="block text-sm font-medium text-slate-700">Puntos máximos (opcional)</label>
      <input type="number" name="max_puntos" min="0"
             value="{{ old('max_puntos', $nivel->max_puntos ?? '') }}"
             class="mt-1 w-full rounded-lg border border-slate-300 bg-white
                    focus:border-slate-500 focus:ring-slate-500" />
      @error('max_puntos')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
      <p class="text-xs text-slate-500 mt-1">Déjalo vacío si es el nivel superior sin límite.</p>
    </div>
  </div>

  <div>
    <label class="block text-sm font-medium text-slate-700">Beneficios (uno por línea)</label>
    <textarea name="beneficios" rows="4"
              class="mt-1 w-full rounded-lg border border-slate-300 bg-white
                     focus:border-slate-500 focus:ring-slate-500">{{ old('beneficios', isset($nivel) ? implode("\n", $nivel->beneficios ?? []) : '') }}</textarea>
    @error('beneficios')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
  </div>

  <div class="flex items-center justify-end gap-3">
    <a href="{{ route('niveles.index') }}"
       class="px-4 py-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50">
       Cancelar
    </a>
    <button type="submit"
            class="px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800">
      {{ $submit }}
    </button>
  </div>
</form>
