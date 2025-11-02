@extends('layouts.app')
@section('title','Nuevo Canje')

@section('content')
<div class="px-6 py-6">
  <div class="bg-white rounded-xl shadow p-6 max-w-3xl">
    <h2 class="text-lg font-semibold mb-4">Nuevo Canje</h2>

    @if ($errors->any())
      <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded p-3">
        <ul class="list-disc pl-5">
          @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form method="post" action="{{ route('usos.store') }}" class="space-y-4">
      @csrf

      <div>
        <label class="block text-sm text-gray-600">Cliente *</label>
        <select name="cliente_id" class="w-full border rounded px-3 py-2" required>
          <option value="">Seleccione…</option>
          @foreach($clientes as $c)
            <option value="{{ $c->id }}" @selected(old('cliente_id')==$c->id)>{{ $c->apellido }}, {{ $c->nombre }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="block text-sm text-gray-600 mb-1">Modo de canje *</label>
        <label class="inline-flex items-center mr-4">
          <input type="radio" name="modo" value="concepto" {{ old('modo','concepto')==='concepto'?'checked':'' }}>
          <span class="ml-2">Por concepto</span>
        </label>
        <label class="inline-flex items-center">
          <input type="radio" name="modo" value="libre" {{ old('modo')==='libre'?'checked':'' }}>
          <span class="ml-2">Puntos libres</span>
        </label>
      </div>

      <div id="blk-concepto">
        <label class="block text-sm text-gray-600">Concepto</label>
        <select name="concepto_uso_id" class="w-full border rounded px-3 py-2">
          <option value="">Seleccione…</option>
          @foreach($conceptos as $x)
            <option value="{{ $x->id }}" @selected(old('concepto_uso_id')==$x->id)>
              {{ $x->descripcion_concepto }} — requiere {{ $x->puntos_requeridos }} pts
            </option>
          @endforeach
        </select>
      </div>

      <div id="blk-puntos" class="hidden">
        <label class="block text-sm text-gray-600">Puntos a usar</label>
        <input type="number" min="1" name="puntos" value="{{ old('puntos') }}" class="w-full border rounded px-3 py-2">
        <p class="text-xs text-slate-500 mt-1">Se aplica FIFO sobre las bolsas disponibles.</p>
      </div>

      <div>
        <label class="block text-sm text-gray-600">Comprobante (opcional)</label>
        <input type="text" name="comprobante" value="{{ old('comprobante') }}" class="w-full border rounded px-3 py-2">
      </div>

      <div class="flex gap-2">
        <button class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Procesar Canje</button>
        <a href="{{ route('usos.index') }}" class="px-4 py-2 rounded border">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
function toggleModo(){
  const modo = document.querySelector('input[name="modo"]:checked')?.value;
  document.getElementById('blk-concepto').classList.toggle('hidden', modo!=='concepto');
  document.getElementById('blk-puntos').classList.toggle('hidden',   modo!=='libre');
}
document.querySelectorAll('input[name="modo"]').forEach(r => r.addEventListener('change', toggleModo));
toggleModo();
</script>
@endsection
