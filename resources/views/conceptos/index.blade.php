@extends('layouts.app')
@section('title','Conceptos de Uso de Puntos')

@section('content')
<div class="px-6 py-6 space-y-5">

  @if(session('ok'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 rounded">
      {{ session('ok') }}
    </div>
  @endif

  {{-- KPIs simples (y por categoría si existe) --}}
  <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-{{ $hasCategoria ? 4 : 3 }} gap-4">
    <div class="bg-white rounded-xl shadow p-4">
      <p class="text-sm text-gray-500">Totales</p>
      <p class="text-2xl font-semibold">{{ $total }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
      <p class="text-sm text-gray-500">Activos</p>
      <p class="text-2xl font-semibold text-emerald-700">{{ $activos }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
      <p class="text-sm text-gray-500">Inactivos</p>
      <p class="text-2xl font-semibold text-slate-700">{{ $inactivos }}</p>
    </div>
    @if($hasCategoria)
      <div class="bg-white rounded-xl shadow p-4">
        <p class="text-sm text-gray-500">Por categoría</p>
        <div class="text-sm mt-1 space-y-1">
          @forelse($byCategoria as $cat => $cant)
            <div class="flex justify-between"><span>{{ $cat ?: '—' }}</span><span class="font-medium">{{ $cant }}</span></div>
          @empty
            <span class="text-slate-500">—</span>
          @endforelse
        </div>
      </div>
    @endif
  </div>

  {{-- Filtros --}}
  <form method="get" class="bg-white rounded-xl shadow p-3 flex items-center gap-3">
    <div class="flex items-center gap-2 flex-1">
      <span class="text-slate-400">🔍</span>
      <input type="text" name="q" value="{{ $q }}" placeholder="Buscar conceptos..."
             class="w-full border border-slate-200 rounded-md px-3 py-2 focus:outline-none focus:ring focus:ring-slate-200">
    </div>

    <select name="estado" class="border border-slate-200 rounded-md px-2 py-2">
      <option value="">Todos</option>
      <option value="1" @selected($estado==='1')>Activos</option>
      <option value="0" @selected($estado==='0')>Inactivos</option>
    </select>

    <select name="per_page" class="border border-slate-200 rounded-md px-2 py-2">
      @foreach([10,25,50] as $n)
        <option value="{{ $n }}" @selected((int)$perPage===$n)>{{ $n }}/pág</option>
      @endforeach
    </select>

    <button class="bg-slate-900 text-white text-sm px-3 py-2 rounded-md hover:bg-slate-800">Filtrar</button>
    <a href="{{ route('conceptos.create') }}" class="bg-slate-900 text-white text-sm px-3 py-2 rounded-lg hover:bg-slate-800">
      ＋ Nuevo Concepto
    </a>
  </form>

  {{-- Tabla --}}
  <div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th class="text-left px-4 py-3">Concepto</th>
            @if($hasCategoria)
              <th class="text-left px-4 py-3">Categoría</th>
            @endif
            <th class="text-left px-4 py-3">Puntos Requeridos</th>
            <th class="text-left px-4 py-3">Estado</th>
            <th class="text-left px-4 py-3">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @forelse($conceptos as $c)
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800">{{ $c->descripcion_concepto }}</div>
              </td>
              @if($hasCategoria)
                <td class="px-4 py-3">
                  <span class="px-2 py-1 text-xs rounded-full border bg-slate-50">
                    {{ $c->categoria ?: '—' }}
                  </span>
                </td>
              @endif
              <td class="px-4 py-3">
                <span class="px-2 py-1 text-xs rounded-full border border-blue-200 bg-blue-50 text-blue-700">
                  {{ number_format($c->puntos_requeridos) }} puntos
                </span>
              </td>
              <td class="px-4 py-3">
                @if($c->activo)
                  <span class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700">Activo</span>
                @else
                  <span class="px-2 py-1 text-xs rounded-full bg-slate-200 text-slate-700">Inactivo</span>
                @endif
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <a href="{{ route('conceptos.show', $c->id) }}" class="p-1.5 rounded border hover:bg-slate-50" title="Ver">👁️</a>
                  <a href="{{ route('conceptos.edit', $c->id) }}" class="p-1.5 rounded border hover:bg-slate-50" title="Editar">✏️</a>
                  <form action="{{ route('conceptos.destroy', $c->id) }}" method="post" onsubmit="return confirm('¿Eliminar concepto?')">
                    @csrf @method('DELETE')
                    <button class="p-1.5 rounded border hover:bg-red-50" title="Eliminar">🗑️</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="{{ $hasCategoria?5:4 }}" class="px-4 py-10 text-center text-slate-500">Sin resultados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="px-4 py-3 border-t">
      {{ $conceptos->links() }}
    </div>
  </div>
</div>
@endsection
