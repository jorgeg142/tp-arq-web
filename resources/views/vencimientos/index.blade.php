@extends('layouts.app')
@section('title','Parametrización de Vencimientos')

@section('content')
<div class="px-6 py-6 space-y-5">

  @if(session('ok'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 rounded">{{ session('ok') }}</div>
  @endif

  {{-- KPIs --}}
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white rounded-xl shadow p-4">
      <p class="text-sm text-gray-500">Períodos Activos</p>
      <p class="text-2xl font-semibold">{{ $periodosActivos }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
      <p class="text-sm text-gray-500">Puntos Afectados</p>
      <p class="text-2xl font-semibold">{{ number_format($puntosAfectados) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
      <p class="text-sm text-gray-500">Próximos a Vencer (28 días)</p>
      <p class="text-2xl font-semibold">{{ number_format($proximosAVencer) }}</p>
    </div>
  </div>
  {{-- Filtro --}}
  <form method="get" class="bg-white rounded-xl shadow p-3 flex items-center gap-3">
    <div class="flex items-center gap-2 flex-1">
      <span class="text-slate-400">🔍</span>
      <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por descripción..."
             class="w-full border border-slate-200 rounded-md px-3 py-2">
    </div>
    <select name="per_page" class="border border-slate-200 rounded-md px-2 py-2">
      @foreach([10,25,50] as $n)
        <option value="{{ $n }}" @selected((int)$perPage===$n)>{{ $n }}/pág</option>
      @endforeach
    </select>
    <button class="bg-slate-900 text-white text-sm px-3 py-2 rounded-md hover:bg-slate-800">Filtrar</button>
    <a href="{{ route('vencimientos.create') }}" class="bg-slate-900 text-white text-sm px-3 py-2 rounded-lg hover:bg-slate-800">
      ＋ Nuevo Período
    </a>
  </form>

  {{-- Tabla --}}
  <div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th class="text-left px-4 py-3">Período</th>
            <th class="text-left px-4 py-3">Fechas de Vigencia</th>
            <th class="text-left px-4 py-3">Validez de Puntos</th>
            <th class="text-left px-4 py-3">Puntos Afectados</th>
            <th class="text-left px-4 py-3">Estado</th>
            <th class="text-left px-4 py-3">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @forelse($periodos as $p)
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800">{{ $p->descripcion ?: 'Período' }}</div>
                <div class="text-xs text-gray-500">ID: {{ $p->id }}</div>
              </td>
              <td class="px-4 py-3">
                <div>{{ \Carbon\Carbon::parse($p->fecha_inicio_validez)->format('d/m/Y') }}</div>
                <div class="text-xs text-gray-500">hasta</div>
                <div>{{ $p->fecha_fin_validez ? \Carbon\Carbon::parse($p->fecha_fin_validez)->format('d/m/Y') : '—' }}</div>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-1 text-xs rounded-full border bg-slate-50">{{ $p->dias_duracion }} días</span>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-1 text-xs rounded-full border border-blue-200 bg-blue-50 text-blue-700">
                  {{ number_format($p->puntos_afectados) }} puntos
                </span>
              </td>
              <td class="px-4 py-3">
                @if($p->activo)
                  <span class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700">Activo</span>
                @else
                  <span class="px-2 py-1 text-xs rounded-full bg-slate-200 text-slate-700">Inactivo</span>
                @endif
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <a href="{{ route('vencimientos.show', $p->id) }}" class="p-1.5 rounded border hover:bg-slate-50" title="Ver">👁️</a>
                  <a href="{{ route('vencimientos.edit', $p->id) }}" class="p-1.5 rounded border hover:bg-slate-50" title="Editar">✏️</a>
                  <form action="{{ route('vencimientos.destroy', $p->id) }}" method="post" onsubmit="return confirm('¿Eliminar período?')">
                    @csrf @method('DELETE')
                    <button class="p-1.5 rounded border hover:bg-red-50" title="Eliminar">🗑️</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Sin períodos.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="px-4 py-3 border-t">{{ $periodos->links() }}</div>
  </div>
</div>
@endsection
