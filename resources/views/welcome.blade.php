<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Bienvenido</title>

  {{-- Tailwind CSS (CDN) --}}
  <script src="https://cdn.tailwindcss.com"></script>

  {{-- Fuente opcional --}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    html, body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji"; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900">

  {{-- Navbar simple --}}
  <header class="sticky top-0 z-20 bg-white/90 backdrop-blur border-b">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
      <a href="{{ url('/') }}" class="font-bold">Fideliza<span class="text-indigo-600">Pro</span></a>
      <nav class="flex items-center gap-2">
        <a href="{{ route('dashboard') }}" class="px-3 py-1.5 rounded hover:bg-slate-100 text-sm">Iniciar sesión</a>
        <a href="" class="px-3 py-1.5 rounded bg-indigo-600 text-white text-sm hover:bg-indigo-700">Crear cuentaa</a>
      </nav>
    </div>
  </header>

  <main class="px-6 py-10 space-y-12">

    {{-- HERO --}}
    <section class="max-w-7xl mx-auto grid md:grid-cols-2 gap-8 items-center">
      <div>
        <h1 class="text-3xl md:text-4xl font-bold leading-tight">
          Sistema de <span class="text-indigo-600">Fidelización</span> para tu negocio
        </h1>
        <p class="mt-3 text-slate-600">
          Otorga puntos por compras, definí reglas, administrá vencimientos y ofrece canjes que enamoran.
          Todo en una interfaz simple y moderna.
        </p>

        <div class="mt-6 flex flex-wrap gap-3">
          <a href=""
             class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Comenzar gratis</a>
          <a href="{{ route('dashboard') }}"
             class="px-4 py-2 rounded-lg border hover:bg-slate-50">Ingresar</a>
        </div>

        <div class="mt-6 flex items-center gap-6 text-sm text-slate-600">
          <div class="flex items-center gap-2"><span>🔐</span> Autenticación segura</div>
          <div class="flex items-center gap-2"><span>⚡</span> Implementación rápida</div>
          <div class="flex items-center gap-2"><span>📈</span> Escalable</div>
        </div>
      </div>

      {{-- Tarjetas mock de métricas (sin JS) --}}
      <div class="bg-white rounded-2xl shadow p-5">
        <div class="grid grid-cols-2 gap-3">
          <div class="rounded-xl border p-4">
            <p class="text-xs text-slate-500">Clientes Activos</p>
            <p class="text-2xl font-semibold mt-1">2,547</p>
            <p class="text-xs text-emerald-600 mt-1">+12% vs mes pasado</p>
          </div>
          <div class="rounded-xl border p-4">
            <p class="text-xs text-slate-500">Puntos Otorgados</p>
            <p class="text-2xl font-semibold mt-1">124,890</p>
            <p class="text-xs text-emerald-600 mt-1">+8%</p>
          </div>
          <div class="rounded-xl border p-4">
            <p class="text-xs text-slate-500">Puntos Canjeados</p>
            <p class="text-2xl font-semibold mt-1">45,230</p>
            <p class="text-xs text-emerald-600 mt-1">+15%</p>
          </div>
          <div class="rounded-xl border p-4">
            <p class="text-xs text-slate-500">Por Vencer (28d)</p>
            <p class="text-2xl font-semibold mt-1">8,450</p>
            <p class="text-xs text-rose-600 mt-1">-3%</p>
          </div>
        </div>

        <div class="mt-5 h-36 rounded-xl bg-gradient-to-r from-indigo-50 to-sky-50 border flex items-center justify-center">
          <span class="text-slate-500 text-sm">Vista previa del dashboard</span>
        </div>
      </div>
    </section>

    {{-- BENEFICIOS --}}
    <section class="max-w-7xl mx-auto">
      <h2 class="text-xl font-semibold mb-4">¿Qué podés hacer?</h2>
      <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow p-5">
          <div class="text-2xl">🧩</div>
          <h3 class="mt-2 font-semibold">Reglas de puntos</h3>
          <p class="text-sm text-slate-600 mt-1">Definí rangos por monto y ratios de otorgamiento.</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5">
          <div class="text-2xl">🎁</div>
          <h3 class="mt-2 font-semibold">Canjes atractivos</h3>
          <p class="text-sm text-slate-600 mt-1">Descuentos, productos y servicios para premiar lealtad.</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5">
          <div class="text-2xl">👥</div>
          <h3 class="mt-2 font-semibold">Gestión de clientes</h3>
          <p class="text-sm text-slate-600 mt-1">Saldos, movimientos y contactos centralizados.</p>
        </div>
      </div>
    </section>

    {{-- CÓMO FUNCIONA --}}
    <section class="max-w-7xl mx-auto">
      <h2 class="text-xl font-semibold mb-4">Cómo funciona</h2>
      <ol class="grid md:grid-cols-3 gap-4 list-decimal list-inside">
        <li class="bg-white rounded-xl shadow p-5">
          <b>Configurar</b>
          <p class="text-sm text-slate-600 mt-1">Creá reglas y vencimientos.</p>
        </li>
        <li class="bg-white rounded-xl shadow p-5">
          <b>Otorgar puntos</b>
          <p class="text-sm text-slate-600 mt-1">Asigná puntos por compras o promos.</p>
        </li>
        <li class="bg-white rounded-xl shadow p-5">
          <b>Canjear</b>
          <p class="text-sm text-slate-600 mt-1">Tus clientes canjean y vos medís el impacto.</p>
        </li>
      </ol>
    </section>

    {{-- CTA --}}
    <section class="max-w-7xl mx-auto">
      <div class="rounded-2xl bg-indigo-600 text-white p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <h3 class="text-lg font-semibold">Empezá hoy mismo</h3>
          <p class="text-indigo-100 text-sm">Configuralo en minutos. Sin tarjeta, sin compromiso.</p>
        </div>
        <div class="flex gap-3">
          <a href="" class="px-4 py-2 rounded-lg bg-white text-indigo-700 hover:bg-indigo-50">Crear cuenta</a>
          <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-lg ring-1 ring-white/60 hover:bg-white/10">Ingresar</a>
        </div>
      </div>
    </section>

  </main>

  {{-- Footer --}}
  <footer class="mt-10 py-6 border-t">
    <div class="max-w-7xl mx-auto px-4 text-center text-xs text-slate-500">
      Hecho con ❤ — {{ date('Y') }}.
      <a href="{{ route('reglas.index') }}" class="underline hover:text-slate-700">Ver demo</a>
    </div>
  </footer>
</body>
</html>
