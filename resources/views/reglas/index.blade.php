@extends('layouts.app')
@section('title','Reglas de Asignación de Puntos')

@section('content')
<div class="px-6 py-6 space-y-5">

  @if(session('ok'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 rounded">
      {{ session('ok') }}
    </div>
  @endif

  <div class="flex items-center justify-between">
    <div>
    </div>
    <a href="{{ route('reglas.create') }}"
       class="bg-slate-900 text-white text-sm px-3 py-2 rounded-lg hover:bg-slate-800">
      ＋ Nueva Regla
    </a>
  </div>

  {{-- Tabla --}}
  <div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th class="text-left px-4 py-3">Nombre / Descripción</th>
            <th class="text-left px-4 py-3">Rango de Monto</th>
            <th class="text-left px-4 py-3">Equivalencia</th>
            <th class="text-left px-4 py-3">Ratio</th>
            <th class="text-left px-4 py-3">Estado</th>
            <th class="text-left px-4 py-3">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @forelse($rules as $r)
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800">{{ $r->descripcion ?: '—' }}</div>
                <div class="text-xs text-gray-500">ID: {{ $r->id }}</div>
              </td>
              <td class="px-4 py-3">{{ $r->rango }}</td>
              <td class="px-4 py-3">{{ $r->eq_text }}</td>
              <td class="px-4 py-3">
                <span class="px-2 py-1 text-xs rounded-full border bg-blue-50 text-blue-700">{{ $r->ratio_x }}x</span>
              </td>
              <td class="px-4 py-3">
                @if($r->activo)
                  <span class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700">Activo</span>
                @else
                  <span class="px-2 py-1 text-xs rounded-full bg-slate-200 text-slate-700">Inactivo</span>
                @endif
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <a href="{{ route('reglas.show', $r->id) }}" class="p-1.5 rounded border hover:bg-slate-50" title="Ver">👁️</a>
                  <a href="{{ route('reglas.edit', $r->id) }}" class="p-1.5 rounded border hover:bg-slate-50" title="Editar">✏️</a>
                  <form action="{{ route('reglas.destroy', $r->id) }}" method="post" onsubmit="return confirm('¿Eliminar regla?')">
                    @csrf @method('DELETE')
                    <button class="p-1.5 rounded border hover:bg-red-50" title="Eliminar">🗑️</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Sin reglas.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
