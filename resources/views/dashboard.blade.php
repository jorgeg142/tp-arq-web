@extends('layouts.app')

@section('title','Dashboard')

@section('content')
<div class="px-6 py-6 space-y-6">

  {{-- KPIs --}}
  <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-5">

    {{-- KPI 1 --}}
    <div class="bg-white rounded-2xl shadow p-5">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-sm text-gray-500">Clientes Activos</p>
          <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($kpis['activeClients'] ?? 0) }}</p>
          <p class="mt-1 text-xs text-emerald-600">+12% vs mes anterior</p>
        </div>
        <div class="text-2xl">👥</div>
      </div>
    </div>

    {{-- KPI 2 --}}
    <div class="bg-white rounded-2xl shadow p-5">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-sm text-gray-500">Puntos Otorgados (mes)</p>
          <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($kpis['granted'] ?? 0) }}</p>
          @php
            $g = (float)($kpis['grantedMoM'] ?? 0);
          @endphp
          <p class="mt-1 text-xs {{ $g>=0 ? 'text-emerald-600':'text-red-600' }}">
            {{ $g>=0?'+':'' }}{{ $g }}% vs mes anterior
          </p>
        </div>
        <div class="text-2xl">🎁</div>
      </div>
    </div>

    {{-- KPI 3 --}}
    <div class="bg-white rounded-2xl shadow p-5">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-sm text-gray-500">Puntos Canjeados (mes)</p>
          <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($kpis['redeemed'] ?? 0) }}</p>
          @php
            $r = (float)($kpis['redeemedMoM'] ?? 0);
          @endphp
          <p class="mt-1 text-xs {{ $r>=0 ? 'text-emerald-600':'text-red-600' }}">
            {{ $r>=0?'+':'' }}{{ $r }}% vs mes anterior
          </p>
        </div>
        <div class="text-2xl">📈</div>
      </div>
    </div>

    {{-- KPI 4 --}}
    <div class="bg-white rounded-2xl shadow p-5">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-sm text-gray-500">Puntos por Vencer (28 días)</p>
          <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($kpis['expiring'] ?? 0) }}</p>
          @php
            $e = (float)($kpis['expiringMoM'] ?? 0);
          @endphp
          <p class="mt-1 text-xs {{ $e>=0 ? 'text-emerald-600':'text-red-600' }}">
            {{ $e>=0?'+':'' }}{{ $e }}% vs periodo anterior
          </p>
        </div>
        <div class="text-2xl">⏰</div>
      </div>
    </div>
  </div>

  {{-- Gráficos --}}
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
  {{-- Tendencia Mensual (Barras) --}}
  <div class="xl:col-span-2 bg-white rounded-2xl shadow p-5">
    <h3 class="font-semibold text-gray-800 mb-2">Tendencia Mensual</h3>
    <div id="trendWrapper" class="relative h-72">  {{-- altura fija --}}
      <canvas id="trendBar" class="absolute inset-0 w-full h-full"></canvas>
    </div>
  </div>

  {{-- Distribución de Canjes (Doughnut) --}}
  <div class="bg-white rounded-2xl shadow p-5">
    <h3 class="font-semibold text-gray-800 mb-2">Distribución de Canjes</h3>
    <div id="distWrapper" class="relative h-72">  {{-- altura fija --}}
      <canvas id="distPie" class="absolute inset-0 w-full h-full"></canvas>
    </div>
    <div class="mt-4 space-y-1">
      @foreach (($distribution['labels'] ?? []) as $i => $lbl)
        <div class="flex justify-between text-sm text-gray-600">
          <span>{{ $lbl }}</span>
          <span>{{ $distribution['pct'][$i] ?? 0 }}%</span>
        </div>
      @endforeach
    </div>
  </div>
</div>

{{-- Ajuste CSS ultra defensivo para canvases en flex --}}
<style>
  /* Garantiza que el canvas nunca “estire” al padre, ocupa el wrapper nomás */
  #trendWrapper canvas, #distWrapper canvas {
    width: 100% !important;
    height: 100% !important;
  }
</style>


  {{-- Actividad Reciente --}}
  <div class="bg-white rounded-2xl shadow p-5">
    <h3 class="font-semibold text-gray-800 mb-4">Actividad Reciente</h3>
    @forelse (($activity ?? []) as $evt)
      <div class="flex items-center justify-between py-3 border-b last:border-0">
        <div class="flex items-center gap-3">
          <span class="text-xl">{{ $evt['icon'] ?? '•' }}</span>
          <div>
            <div class="text-sm font-medium text-gray-800">{{ $evt['title'] ?? '' }}</div>
            <div class="text-xs text-gray-500">{{ $evt['subtitle'] ?? '' }}</div>
          </div>
        </div>
        <div class="text-xs text-gray-400">{{ $evt['time'] ?? '' }}</div>
      </div>
    @empty
      <p class="text-sm text-gray-500">Sin actividad reciente.</p>
    @endforelse
  </div>
</div>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const trendLabels  = @json($trend['labels'] ?? []);
  const assignedData = @json($trend['assignedData'] ?? []);
  const redeemedData = @json($trend['redeemedData'] ?? []);

  const distLabels = @json($distribution['labels'] ?? []);
  const distValues = @json($distribution['values'] ?? []);

  // Barras
  new Chart(document.getElementById('trendBar'), {
    type: 'bar',
    data: {
      labels: trendLabels,
      datasets: [
        { label: 'Asignados', data: assignedData, backgroundColor: 'rgba(37, 99, 235, .6)', borderColor: 'rgba(37, 99, 235, 1)', borderWidth: 1 },
        { label: 'Canjeados', data: redeemedData, backgroundColor: 'rgba(16, 185, 129, .6)', borderColor: 'rgba(16, 185, 129, 1)', borderWidth: 1 },
      ]
    },
    options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
  });

  // Doughnut
  new Chart(document.getElementById('distPie'), {
    type: 'doughnut',
    data: { labels: distLabels, datasets: [{ data: distValues, borderWidth: 1 }] },
    options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
  });
</script>
@endsection
