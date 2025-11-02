@extends('layouts.app')
@section('title','Bolsa de Puntos')

@section('content')
<div class="px-6 py-6 space-y-5">

    {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl shadow p-4">
        <p class="text-sm text-gray-500">Puntos Activos</p>
        <p class="text-2xl font-semibold">{{ number_format($kpiActivos) }}</p>
      </div>
      <div class="bg-white rounded-xl shadow p-4">
        <p class="text-sm text-gray-500">Puntos Utilizados</p>
        <p class="text-2xl font-semibold">{{ number_format($kpiUtilizados) }}</p>
      </div>
      <div class="bg-white rounded-xl shadow p-4">
        <p class="text-sm text-gray-500">Por Vencer (28 días)</p>
        <p class="text-2xl font-semibold">{{ number_format($kpiPorVencer) }}</p>
      </div>
      <div class="bg-white rounded-xl shadow p-4">
        <p class="text-sm text-gray-500">Bolsas Activas</p>
        <p class="text-2xl font-semibold">{{ number_format($kpiBolsasActivas) }}</p>
      </div>
    </div>

    {{-- Gráficos --}}
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-xl shadow p-4">
      <p class="text-sm text-gray-600 mb-2">Evolución de Puntos</p>
      <div class="relative h-64 lg:h-72">   {{-- alto fijo del contenedor --}}
        <canvas id="evolChart"></canvas>
      </div>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
      <p class="text-sm text-gray-600 mb-2">Puntos por Cliente (Top 5)</p>
      <div class="relative h-64 lg:h-72">   {{-- alto fijo del contenedor --}}
        <canvas id="topChart"></canvas>
      </div>
    </div>
  </div>

    {{-- Filtros + Export --}}
    <form method="get" class="bg-white rounded-xl shadow p-3 flex flex-col md:flex-row gap-3 items-center">
      <div class="flex items-center gap-2 flex-1 w-full">
        <span class="text-slate-400">🔍</span>
        <input type="text" name="q" value="{{ $q }}" placeholder="Buscar cliente o fuente…"
              class="w-full border border-slate-200 rounded-md px-3 py-2">
      </div>

      <select name="cliente" class="border border-slate-200 rounded-md px-2 py-2">
        <option value="">Todos los clientes</option>
        @foreach($clientes as $c)
          <option value="{{ $c->id }}" @selected((int)$cliente === $c->id)>{{ $c->apellido }}, {{ $c->nombre }}</option>
        @endforeach
      </select>

      <select name="estado" class="border border-slate-200 rounded-md px-2 py-2">
        <option value="">Todos los estados</option>
        @foreach(['activo'=>'Activo','vencido'=>'Vencido','agotado'=>'Agotado'] as $k=>$v)
          <option value="{{ $k }}" @selected($estado===$k)>{{ $v }}</option>
        @endforeach
      </select>

      <button class="bg-slate-900 text-white text-sm px-3 py-2 rounded-md hover:bg-slate-800">Aplicar</button>

      <a href="{{ route('bolsas.export', request()->query()) }}"
        class="ml-auto border px-3 py-2 rounded-md text-sm hover:bg-slate-50">Exportar Reporte</a>
      <a href="{{ route('bolsas.create') }}" class="bg-slate-900 text-white text-sm px-3 py-2 rounded-lg hover:bg-slate-800">
        ＋ Nueva Bolsa
      </a>
    </form>

    {{-- Tabla --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-slate-600">
            <tr>
              <th class="text-left px-4 py-3">Cliente</th>
              <th class="text-left px-4 py-3">Fecha Asignación</th>
              <th class="text-left px-4 py-3">Fecha Vencimiento</th>
              <th class="text-left px-4 py-3">Puntos Iniciales</th>
              <th class="text-left px-4 py-3">Puntos Usados</th>
              <th class="text-left px-4 py-3">Puntos Restantes</th>
              <th class="text-left px-4 py-3">Fuente</th>
              <th class="text-left px-4 py-3">Estado</th>
              <th class="text-left px-4 py-3">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            @forelse($rows as $r)
              <tr class="hover:bg-slate-50">
                <td class="px-4 py-3">
                  <div class="font-medium text-gray-800">{{ $r->nombre }} {{ $r->apellido }}</div>
                  <div class="text-xs text-gray-500">ID: {{ $r->cliente_id }}</div>
                </td>
                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($r->fecha_asignacion)->format('d/m/Y') }}</td>
                <td class="px-4 py-3">
                  {{ $r->fecha_caducidad ? \Carbon\Carbon::parse($r->fecha_caducidad)->format('d/m/Y') : '—' }}
                </td>
                <td class="px-4 py-3">
                  <span class="px-2 py-1 text-xs rounded-full border bg-slate-50">{{ $r->puntaje_asignado }}</span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <span>{{ $r->puntaje_utilizado }}</span>
                    @php $pct = $r->puntaje_asignado>0 ? round(($r->puntaje_utilizado/$r->puntaje_asignado)*100) : 0; @endphp
                    <div class="w-24 h-2 bg-slate-100 rounded">
                      <div class="h-2 rounded bg-sky-400" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="text-xs text-slate-500">{{ $pct }}%</span>
                  </div>
                </td>
                <td class="px-4 py-3">{{ $r->saldo_puntos }}</td>
                <td class="px-4 py-3">{{ $r->origen ?? '—' }}</td>
                <td class="px-4 py-3">
                  @if($r->estado_calc === 'activo')
                    <span class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700">Activo</span>
                  @elseif($r->estado_calc === 'vencido')
                    <span class="px-2 py-1 text-xs rounded-full bg-orange-100 text-orange-700">Vencido</span>
                  @else
                    <span class="px-2 py-1 text-xs rounded-full bg-slate-200 text-slate-700">Agotado</span>
                  @endif
                </td>
                <td class="px-4 py-3">
                    {{-- VER --}}
                    <button class="p-1.5 rounded border hover:bg-slate-50 btn-ver-bolsa"
                            data-url="{{ route('bolsas.show', $r->id) }}" title="Ver">👁️</button>

                    {{-- EDITAR --}}
                    <a href="{{ route('bolsas.edit',$r->id) }}" class="p-1.5 rounded border hover:bg-slate-50" title="Editar">✏️</a>

                    {{-- ELIMINAR --}}
                    <form action="{{ route('bolsas.destroy',$r->id) }}" method="post" class="inline"
                          onsubmit="return confirm('¿Eliminar esta bolsa? Esta acción no se puede deshacer.');">
                      @csrf @method('DELETE')
                      <button class="p-1.5 rounded border hover:bg-rose-50" title="Eliminar">🗑️</button>
                    </form>
                  </td>

                  {{-- ojo -> usa /detalle --}}
                  <button class="p-1.5 rounded border hover:bg-slate-50 btn-ver-bolsa"
                          data-url="{{ route('bolsas.show', $r->id) }}" title="Ver">👁️</button>
                </td>
              </tr>
            @empty
              <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">Sin resultados.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="px-4 py-3 border-t">
        {{ $rows->links() }}
      </div>
    </div>

</div>

{{-- Modal Detalle Bolsa --}}
<div id="bolsaModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40" data-close="1"></div>
  <div class="absolute inset-x-0 top-10 mx-auto w-11/12 max-w-3xl">
    <div class="bg-white rounded-xl shadow-xl overflow-hidden">
      <div class="px-5 py-3 border-b flex items-center justify-between">
        <h3 class="font-semibold">Detalle de Bolsa</h3>
        <button class="px-2 py-1 rounded hover:bg-slate-100" data-close="1">✖</button>
      </div>
      <div class="p-5 space-y-4">
        <div class="grid md:grid-cols-2 gap-4 text-sm">
          <div><span class="text-slate-500">Cliente</span>
            <div id="m-cliente" class="font-medium"></div>
            <div id="m-doc" class="text-xs text-slate-500"></div>
          </div>
          <div><span class="text-slate-500">Estado</span>
            <div id="m-estado" class="font-medium"></div>
          </div>
          <div><span class="text-slate-500">Fecha Asignación</span>
            <div id="m-asign"></div>
          </div>
          <div><span class="text-slate-500">Fecha Vencimiento</span>
            <div id="m-venc"></div>
          </div>
          <div><span class="text-slate-500">Puntos</span>
            <div><b>Asignado:</b> <span id="m-asig"></span> ·
                 <b>Usado:</b> <span id="m-uso"></span> ·
                 <b>Saldo:</b> <span id="m-saldo"></span></div>
          </div>
          <div><span class="text-slate-500">Origen</span>
            <div id="m-origen"></div>
          </div>
          <div class="md:col-span-2"><span class="text-slate-500">Parámetro de Vencimiento</span>
            <div id="m-param"></div>
          </div>
        </div>

        <div>
          <div class="text-sm text-slate-600 mb-2">Usos de esta bolsa</div>
          <div id="m-det" class="border rounded-lg overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="text-left px-3 py-2">Fecha</th>
                  <th class="text-left px-3 py-2">Concepto</th>
                  <th class="text-left px-3 py-2">Puntos</th>
                </tr>
              </thead>
              <tbody id="m-det-body"></tbody>
            </table>
          </div>
          <div id="m-det-empty" class="text-sm text-slate-500 hidden">Sin usos registrados.</div>
        </div>
      </div>
      <div class="px-5 py-3 border-t text-right">
        <button class="px-4 py-2 rounded border" data-close="1">Cerrar</button>
      </div>
    </div>
  </div>
</div>


{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
<script>
new Chart(document.getElementById('evolChart'), {
  type: 'line',
  data: {
    labels: @json($labels),
    datasets: [
      { label:'Asignados', data:@json($asig), borderWidth:2, tension:.3 },
      { label:'Usados',     data:@json($used), borderWidth:2, tension:.3 }
    ]
  },
  options: { responsive:true, maintainAspectRatio:false }
});

new Chart(document.getElementById('topChart'), {
  type: 'bar',
  data: {
    labels: @json($topClientes->map(fn($i)=>$i->nombre.' '.$i->apellido)),
    datasets: [{ label:'Saldo', data:@json($topClientes->pluck('saldo')), borderWidth:1 }]
  },
  options: { indexAxis:'y', responsive:true, maintainAspectRatio:false }
});
</script>
<script>
document.addEventListener('click', async (e) => {
  // Abrir modal
  const btn = e.target.closest('.btn-ver-bolsa');
  if (btn) {
    const url = btn.dataset.url;
    const res = await fetch(url);
    if (!res.ok) return alert('No se pudo cargar el detalle');
    const { bolsa, detalles } = await res.json();

    // Cargar datos
    const fmt = (d) => d ? new Date(d).toLocaleDateString('es-PY') : '—';
    document.getElementById('m-cliente').textContent = `${bolsa.nombre ?? ''} ${bolsa.apellido ?? ''}`.trim();
    document.getElementById('m-doc').textContent     = bolsa.numero_documento ? 'Doc: ' + bolsa.numero_documento : '';
    document.getElementById('m-estado').textContent  = bolsa.estado;
    document.getElementById('m-asign').textContent   = fmt(bolsa.fecha_asignacion);
    document.getElementById('m-venc').textContent    = fmt(bolsa.fecha_caducidad);
    document.getElementById('m-asig').textContent    = bolsa.puntaje_asignado;
    document.getElementById('m-uso').textContent     = bolsa.puntaje_utilizado;
    document.getElementById('m-saldo').textContent   = bolsa.saldo_puntos;
    document.getElementById('m-origen').textContent  = bolsa.origen ?? '—';

    const param = [];
    if (bolsa.param_desc) param.push(bolsa.param_desc);
    if (bolsa.dias_duracion != null) param.push(`${bolsa.dias_duracion} días`);
    if (bolsa.fecha_inicio_validez) param.push(`desde ${fmt(bolsa.fecha_inicio_validez)}`);
    if (bolsa.fecha_fin_validez)    param.push(`hasta ${fmt(bolsa.fecha_fin_validez)}`);
    document.getElementById('m-param').textContent = param.length ? param.join(' · ') : '—';

    // Detalle usos
    const body = document.getElementById('m-det-body');
    body.innerHTML = '';
    if (detalles.length) {
      document.getElementById('m-det').classList.remove('hidden');
      document.getElementById('m-det-empty').classList.add('hidden');
      detalles.forEach(d => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="px-3 py-2">${fmt(d.fecha_detalle)}</td>
          <td class="px-3 py-2">${d.concepto}</td>
          <td class="px-3 py-2">${d.puntaje_utilizado}</td>
        `;
        body.appendChild(tr);
      });
    } else {
      document.getElementById('m-det').classList.add('hidden');
      document.getElementById('m-det-empty').classList.remove('hidden');
    }

    // Mostrar modal
    document.getElementById('bolsaModal').classList.remove('hidden');
  }

  // Cerrar modal
  const toClose = e.target.closest('[data-close]');
  if (toClose) {
    document.getElementById('bolsaModal').classList.add('hidden');
  }
});
</script>
@endsection
