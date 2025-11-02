@extends('layouts.app')
@section('title','Uso de Puntos')

@section('content')
<div class="px-6 py-6 space-y-5">
  @if(session('ok'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 rounded">{{ session('ok') }}</div>
  @endif
  @if ($errors->has('del'))
    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 rounded">{{ $errors->first('del') }}</div>
  @endif

  {{-- KPIs --}}
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl shadow p-4">
      <p class="text-sm text-gray-500">Canjes Hoy</p>
      <p class="text-2xl font-semibold">{{ number_format($canjesHoy) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
      <p class="text-sm text-gray-500">Puntos Canjeados</p>
      <p class="text-2xl font-semibold">{{ number_format($totalCanjeados) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
      <p class="text-sm text-gray-500">Pendientes</p>
      <p class="text-2xl font-semibold">{{ number_format($pendientes) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
      <p class="text-sm text-gray-500">Valor Promedio</p>
      <p class="text-2xl font-semibold">{{ number_format($promedio) }} pts</p>
    </div>
  </div>

  {{-- Filtros --}}
  <form method="get" class="bg-white rounded-xl shadow p-3 flex gap-3 items-center">
    <div class="flex items-center gap-2 flex-1">
      <span class="text-slate-400">🔍</span>
      <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por cliente, concepto o ID..."
             class="w-full border border-slate-200 rounded-md px-3 py-2">
    </div>
    <select name="estado" class="border border-slate-200 rounded-md px-2 py-2">
      <option value="">Todos los estados</option>
      @foreach(['COMPLETADO'=>'Completado','PENDIENTE'=>'Pendiente'] as $k=>$v)
        <option value="{{ $k }}" @selected($estado===$k)>{{ $v }}</option>
      @endforeach
    </select>
    <button class="bg-slate-900 text-white text-sm px-3 py-2 rounded-md hover:bg-slate-800">Aplicar</button>
     <a href="{{ route('usos.create') }}" class="bg-slate-900 text-white text-sm px-3 py-2 rounded-lg hover:bg-slate-800">
      ＋ Nuevo Canje
    </a>
  </form>

  {{-- Tabla --}}
  <div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th class="text-left px-4 py-3">ID Transacción</th>
            <th class="text-left px-4 py-3">Cliente</th>
            <th class="text-left px-4 py-3">Concepto</th>
            <th class="text-left px-4 py-3">Puntos Usados</th>
            <th class="text-left px-4 py-3">Fecha</th>
            <th class="text-left px-4 py-3">Estado</th>
            <th class="text-left px-4 py-3">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @forelse($rows as $r)
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3">TXN-{{ str_pad($r->id,3,'0',STR_PAD_LEFT) }}</td>
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800">{{ $r->nombre }} {{ $r->apellido }}</div>
                <div class="text-xs text-gray-500">ID: {{ $r->cliente_id }}</div>
              </td>
              <td class="px-4 py-3">{{ $r->concepto }}</td>
              <td class="px-4 py-3">
                <span class="px-2 py-1 text-xs rounded-full border bg-rose-50 text-rose-700">-{{ $r->puntaje_utilizado }}</span>
              </td>
              <td class="px-4 py-3">{{ \Carbon\Carbon::parse($r->fecha)->format('d/m/Y H:i') }}</td>
              <td class="px-4 py-3">
                @if($r->estado==='COMPLETADO')
                  <span class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700">Completado</span>
                @else
                  <span class="px-2 py-1 text-xs rounded-full bg-amber-100 text-amber-700">Pendiente</span>
                @endif
              </td>
                <td class="px-4 py-3">
                    {{-- Ver --}}
                    <button class="p-1.5 rounded border hover:bg-slate-50 btn-ver-uso"
                            data-url="{{ route('usos.show', $r->id) }}" title="Ver">👁️</button>

                    {{-- Editar --}}
                    <a href="{{ route('usos.edit', $r->id) }}" class="p-1.5 rounded border hover:bg-slate-50" title="Editar">✏️</a>

                    {{-- Eliminar (protegido) --}}
                    <form action="{{ route('usos.destroy', $r->id) }}" method="post" class="inline"
                            onsubmit="return confirm('¿Eliminar este canje? Esto puede desbalancear saldos si ya impactó en bolsas.');">
                        @csrf @method('DELETE')
                        <button class="p-1.5 rounded border hover:bg-rose-50" title="Eliminar">🗑️</button>
                    </form>
                </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Sin resultados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="px-4 py-3 border-t">{{ $rows->links() }}</div>
  </div>
</div>

{{-- Modal Detalle --}}
<div id="usoModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40" data-close="1"></div>
  <div class="absolute inset-x-0 top-10 mx-auto w-11/12 max-w-3xl">
    <div class="bg-white rounded-xl shadow-xl overflow-hidden">
      <div class="px-5 py-3 border-b flex items-center justify-between">
        <h3 class="font-semibold">Detalle de Canje</h3>
        <button class="px-2 py-1 rounded hover:bg-slate-100" data-close="1">✖</button>
      </div>
      <div class="p-5 space-y-4">
        <div class="grid md:grid-cols-2 gap-4 text-sm">
          <div><span class="text-slate-500">Cliente</span> <div id="d-cliente" class="font-medium"></div></div>
          <div><span class="text-slate-500">Estado</span>  <div id="d-estado"></div></div>
          <div><span class="text-slate-500">Fecha</span>   <div id="d-fecha"></div></div>
          <div><span class="text-slate-500">Concepto</span><div id="d-concepto"></div></div>
          <div class="md:col-span-2"><span class="text-slate-500">Comprobante</span><div id="d-comp"></div></div>
        </div>

        <div>
          <div class="text-sm text-slate-600 mb-2">Aplicación por bolsas (FIFO)</div>
          <div class="border rounded-lg overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="text-left px-3 py-2">Fecha Asignación</th>
                  <th class="text-left px-3 py-2">Vencimiento</th>
                  <th class="text-left px-3 py-2">Origen</th>
                  <th class="text-left px-3 py-2">Puntos Usados</th>
                </tr>
              </thead>
              <tbody id="d-body"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="px-5 py-3 border-t text-right">
        <button class="px-4 py-2 rounded border" data-close="1">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.btn-ver-uso');
  if (btn) {
    const res = await fetch(btn.dataset.url);
    if (!res.ok) return alert('No se pudo cargar el detalle');
    const { cabecera, detalles } = await res.json();
    const fmt = (d)=> d ? new Date(d).toLocaleString('es-PY') : '—';

    document.getElementById('d-cliente').textContent = `${cabecera.nombre} ${cabecera.apellido}`;
    document.getElementById('d-estado').textContent  = cabecera.estado;
    document.getElementById('d-fecha').textContent   = fmt(cabecera.fecha);
    document.getElementById('d-concepto').textContent= cabecera.concepto;
    document.getElementById('d-comp').textContent    = cabecera.comprobante ?? '—';

    const body = document.getElementById('d-body');
    body.innerHTML = '';
    detalles.forEach(d => {
      const tr = document.createElement('tr');
      const f1 = d.fecha_asignacion ? new Date(d.fecha_asignacion).toLocaleDateString('es-PY') : '—';
      const f2 = d.fecha_caducidad ? new Date(d.fecha_caducidad).toLocaleDateString('es-PY') : '—';
      tr.innerHTML = `
        <td class="px-3 py-2">${f1}</td>
        <td class="px-3 py-2">${f2}</td>
        <td class="px-3 py-2">${d.origen ?? '—'}</td>
        <td class="px-3 py-2">-${d.puntaje_utilizado}</td>
      `;
      body.appendChild(tr);
    });

    document.getElementById('usoModal').classList.remove('hidden');
  }

  if (e.target.closest('[data-close]')) {
    document.getElementById('usoModal').classList.add('hidden');
  }
});
</script>
@endsection
