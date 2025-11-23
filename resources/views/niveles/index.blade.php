@extends('layouts.app')
@section('title','Niveles de Fidelización')
@section('subtitle','Administra los rangos de puntos y beneficios de cada nivel')

@section('content')
<div class="px-6 py-6 space-y-4">
  <div class="bg-white rounded-xl shadow p-6 space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Escala de niveles</h2>
        <p class="text-sm text-slate-500">Configura los mínimos y máximos de puntos para cada nivel y sus beneficios.</p>
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('niveles.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-slate-900 text-white hover:bg-slate-800">➕ Crear nivel</a>
        <a href="{{ route('clientes.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-md border border-slate-200 hover:bg-slate-50 text-slate-700">👥 Ver clientes</a>
      </div>
    </div>

    @if (session('ok'))
      <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm">{{ session('ok') }}</div>
    @endif

    <div class="overflow-x-auto border border-slate-100 rounded-lg">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-slate-600">
          <tr>
            <th class="px-4 py-3">Nombre</th>
            <th class="px-4 py-3">Rango de puntos</th>
            <th class="px-4 py-3">Beneficios</th>
            <th class="px-4 py-3">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @forelse($niveles as $nivel)
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3 align-top">
                <div class="font-semibold text-slate-800">{{ $nivel->nombre }}</div>
                <div class="text-xs text-slate-500">Slug: {{ $nivel->slug }}</div>
                @if($nivel->descripcion)
                  <div class="text-xs text-slate-500 mt-1">{{ $nivel->descripcion }}</div>
                @endif
              </td>
              <td class="px-4 py-3 align-top">
                <div class="font-medium text-slate-800">Desde {{ number_format($nivel->min_puntos, 0, ',', '.') }} pts</div>
                <div class="text-xs text-slate-500">{{ $nivel->max_puntos ? 'Hasta '.number_format($nivel->max_puntos, 0, ',', '.').' pts' : 'Sin límite superior' }}</div>
              </td>
              <td class="px-4 py-3 align-top">
                @if(!empty($nivel->beneficios))
                  <ul class="list-disc list-inside space-y-1 text-slate-700">
                    @foreach($nivel->beneficios as $beneficio)
                      <li>{{ $beneficio }}</li>
                    @endforeach
                  </ul>
                @else
                  <span class="text-sm text-slate-500">Sin beneficios cargados.</span>
                @endif
              </td>
              <td class="px-4 py-3 align-top">
                <div class="flex items-center gap-2">
                  <a href="{{ route('niveles.edit', $nivel->id) }}" class="px-3 py-1.5 rounded-md border hover:bg-slate-50 text-slate-700">✏️ Editar</a>
                  <form action="{{ route('niveles.destroy', $nivel->id) }}" method="post" onsubmit="return confirm('¿Eliminar nivel? Los clientes quedarán sin nivel asignado.');">
                    @csrf @method('DELETE')
                    <button class="px-3 py-1.5 rounded-md border border-red-200 text-red-700 hover:bg-red-50">🗑️ Eliminar</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="px-4 py-10 text-center text-slate-500">No hay niveles creados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="px-4 py-3 border-t">
      {{ $niveles->links() }}
    </div>
  </div>
</div>
@endsection
