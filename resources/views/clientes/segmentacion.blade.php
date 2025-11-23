@extends('layouts.app')
@section('title','Segmentación de Clientes')
@section('subtitle','Define criterios y genera grupos para promociones personalizadas')

@section('content')
<div class="px-6 py-6 space-y-6">
  <form method="get" class="bg-white rounded-xl shadow p-4 space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 items-end">
      <div class="col-span-1 md:col-span-2 lg:col-span-3 flex items-center gap-2">
        <span class="text-slate-400">🔍</span>
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               placeholder="Buscar por nombre, email o documento"
               class="w-full border border-slate-200 rounded-md px-3 py-2 focus:outline-none focus:ring focus:ring-slate-200" />
      </div>
      <div class="flex gap-3 justify-end">
        <a href="{{ route('clientes.segmentacion') }}" class="text-sm px-3 py-2 rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50">Limpiar</a>
        <button class="bg-slate-900 text-white text-sm px-4 py-2 rounded-md hover:bg-slate-800">Aplicar filtros</button>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="space-y-1">
        <label class="text-xs text-slate-500">Edad (mín - máx)</label>
        <div class="flex gap-2">
          <input type="number" name="edad_min" value="{{ $filters['edad_min'] ?? '' }}" min="0" max="120"
                 class="w-full border border-slate-200 rounded-md px-3 py-2" placeholder="Desde" />
          <input type="number" name="edad_max" value="{{ $filters['edad_max'] ?? '' }}" min="0" max="120"
                 class="w-full border border-slate-200 rounded-md px-3 py-2" placeholder="Hasta" />
        </div>
      </div>

      <div class="space-y-1">
        <label class="text-xs text-slate-500">Ubicación / nacionalidad</label>
        <select name="nacionalidad" class="w-full border border-slate-200 rounded-md px-3 py-2">
          <option value="">Todas</option>
          @foreach($nacionalidades as $n)
            <option value="{{ $n }}" @selected(($filters['nacionalidad'] ?? '') === $n)>{{ $n }}</option>
          @endforeach
        </select>
      </div>

      <div class="space-y-1">
        <label class="text-xs text-slate-500">Monto total comprado</label>
        <div class="flex gap-2">
          <input type="number" step="0.01" min="0" name="monto_min" value="{{ $filters['monto_min'] ?? '' }}" class="w-full border border-slate-200 rounded-md px-3 py-2" placeholder="Mín" />
          <input type="number" step="0.01" min="0" name="monto_max" value="{{ $filters['monto_max'] ?? '' }}" class="w-full border border-slate-200 rounded-md px-3 py-2" placeholder="Máx" />
        </div>
      </div>

      <div class="space-y-1">
        <label class="text-xs text-slate-500">Estado</label>
        <select name="estado" class="w-full border border-slate-200 rounded-md px-3 py-2">
          <option value="">Todos</option>
          <option value="1" @selected(($filters['estado'] ?? '')==='1')>Activos</option>
          <option value="0" @selected(($filters['estado'] ?? '')==='0')>Inactivos</option>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
      <div class="space-y-1">
        <label class="text-xs text-slate-500">Compras mínimas registradas</label>
        <input type="number" min="0" name="compras_min" value="{{ $filters['compras_min'] ?? '' }}" class="w-full border border-slate-200 rounded-md px-3 py-2" placeholder="Ej: 3" />
      </div>

      <div class="space-y-1">
        <label class="text-xs text-slate-500">Puntos acumulados mínimos</label>
        <input type="number" min="0" name="puntos_min" value="{{ $filters['puntos_min'] ?? '' }}" class="w-full border border-slate-200 rounded-md px-3 py-2" placeholder="Ej: 1000" />
      </div>

      <div class="space-y-1">
        <label class="text-xs text-slate-500">Orden</label>
        <select name="orden" class="w-full border border-slate-200 rounded-md px-3 py-2">
          <option value="monto_desc" @selected(($filters['orden'] ?? 'monto_desc')==='monto_desc')>Mayor gasto</option>
          <option value="compras_desc" @selected(($filters['orden'] ?? 'monto_desc')==='compras_desc')>Más compras</option>
          <option value="puntos_desc" @selected(($filters['orden'] ?? 'monto_desc')==='puntos_desc')>Más puntos asignados</option>
          <option value="edad_desc" @selected(($filters['orden'] ?? 'monto_desc')==='edad_desc')>Mayor edad</option>
          <option value="recientes" @selected(($filters['orden'] ?? 'monto_desc')==='recientes')>Alta más reciente</option>
        </select>
      </div>

      <div class="space-y-1">
        <label class="text-xs text-slate-500">Resultados por página</label>
        <select name="per_page" class="w-full border border-slate-200 rounded-md px-3 py-2">
          @foreach([10,25,50] as $n)
            <option value="{{ $n }}" @selected($perPage===$n)>{{ $n }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </form>

  <div class="bg-white rounded-xl shadow">
    <div class="p-4 flex flex-wrap justify-between items-start gap-3 border-b">
      <div>
        <p class="text-sm text-slate-500">Clientes encontrados</p>
        <p class="text-2xl font-semibold text-slate-800">{{ $clientes->total() }}</p>
        <p class="text-xs text-slate-500">Segmentá usando edad, ubicación y comportamiento de compra para campañas más precisas.</p>
      </div>
      <div class="flex flex-wrap gap-2 text-xs">
        @if(($filters['edad_min'] ?? '') !== '' || ($filters['edad_max'] ?? '') !== '')
          <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700">Edad: {{ $filters['edad_min'] ?? '—' }} - {{ $filters['edad_max'] ?? '—' }}</span>
        @endif
        @if(($filters['nacionalidad'] ?? '') !== '')
          <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700">{{ $filters['nacionalidad'] }}</span>
        @endif
        @if(($filters['monto_min'] ?? '') !== '' || ($filters['monto_max'] ?? '') !== '')
          <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700">Compra: {{ $filters['monto_min'] ?? '0' }} - {{ $filters['monto_max'] ?? '∞' }}</span>
        @endif
        @if(($filters['compras_min'] ?? '') !== '')
          <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700">Compras ≥ {{ $filters['compras_min'] }}</span>
        @endif
        @if(($filters['puntos_min'] ?? '') !== '')
          <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700">Puntos ≥ {{ $filters['puntos_min'] }}</span>
        @endif
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th class="text-left px-4 py-3">Cliente</th>
            <th class="text-left px-4 py-3">Edad</th>
            <th class="text-left px-4 py-3">Ubicación</th>
            <th class="text-left px-4 py-3">Historial de compras</th>
            <th class="text-left px-4 py-3">Puntos asignados</th>
            <th class="text-left px-4 py-3">Nivel</th>
            <th class="text-left px-4 py-3">Estado</th>
            <th class="text-left px-4 py-3">Contacto</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @forelse ($clientes as $c)
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3">
                <div class="font-semibold text-gray-800">{{ $c->nombre }} {{ $c->apellido }}</div>
                <div class="text-xs text-gray-500">Doc: {{ $c->numero_documento ?: '—' }}</div>
              </td>
              <td class="px-4 py-3">{{ $c->edad !== null ? $c->edad.' años' : 'Sin dato' }}</td>
              <td class="px-4 py-3">{{ $c->nacionalidad ?: 'No informado' }}</td>
              <td class="px-4 py-3">
                <div class="text-gray-800 font-semibold">{{ number_format((float)$c->monto_total, 0, ',', '.') }} Gs.</div>
                <div class="text-xs text-gray-500">{{ $c->compras }} compra(s)</div>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-1 text-xs rounded-full border border-blue-200 bg-blue-50 text-blue-700">
                  {{ number_format((float)$c->puntos_asignados, 0, ',', '.') }} pts
                </span>
              </td>
              <td class="px-4 py-3 space-y-1">
                <div class="flex items-center gap-2">
                  <span class="px-2 py-1 text-xs rounded-full border border-amber-200 bg-amber-50 text-amber-700">{{ $c->nivel['nombre'] }}</span>
                  <span class="text-xs text-slate-500">{{ $c->nivel['progreso'] }}% hacia el próximo nivel</span>
                </div>
                @if($c->nivel['siguiente'])
                  <div class="text-xs text-slate-500">Faltan {{ number_format($c->nivel['siguiente']['faltan'], 0, ',', '.') }} pts para {{ $c->nivel['siguiente']['nombre'] }}</div>
                @else
                  <div class="text-xs text-emerald-600">Nivel máximo alcanzado</div>
                @endif
              </td>
              <td class="px-4 py-3">
                @if ($c->activo)
                  <span class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700">Activo</span>
                @else
                  <span class="px-2 py-1 text-xs rounded-full bg-slate-200 text-slate-700">Inactivo</span>
                @endif
              </td>
              <td class="px-4 py-3 text-sm text-slate-700">
                <div>{{ $c->email ?: '—' }}</div>
                <div class="text-xs text-gray-500">{{ $c->telefono ?: '—' }}</div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="px-4 py-10 text-center text-slate-500">No se encontraron clientes para los criterios seleccionados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="px-4 py-3 border-t">{{ $clientes->links() }}</div>
  </div>
</div>
@endsection
