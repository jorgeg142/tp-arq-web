<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>@yield('title','Sistema de Fidelización')</title>

  {{-- Tailwind por CDN (rápido y sin build) --}}
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" type="image/png">
</head>
<body class="bg-gray-100 text-gray-800 font-sans">

  <div class="min-h-screen flex">
    {{-- Sidebar --}}
    <aside class="w-64 bg-slate-900 text-white flex flex-col">
      <div class="px-6 py-5 border-b border-slate-700">
        <h2 class="text-lg font-semibold">Sistema de Fidelización</h2>
        <p class="text-xs text-slate-400">Gestión de puntos y clientes</p>
      </div>

      <nav class="flex-1 mt-5 space-y-1">
        <a href="{{ route('dashboard') }}"
           class="block px-5 py-2.5 rounded-md hover:bg-slate-800 {{ request()->is('dashboard') ? 'bg-slate-800' : '' }}">
          📊 Dashboard
        </a>
        <a class="block px-5 py-2.5 rounded-md hover:bg-slate-800" href={{ route('clientes.index') }}>👥 Gestión de Clientes</a>
        <a class="block px-5 py-2.5 rounded-md hover:bg-slate-800" href="#">🎯 Conceptos de Puntos</a>
        <a class="block px-5 py-2.5 rounded-md hover:bg-slate-800" href="#">⚙️ Reglas de Asignación</a>
        <a class="block px-5 py-2.5 rounded-md hover:bg-slate-800" href="#">📅 Vencimientos</a>
        <a class="block px-5 py-2.5 rounded-md hover:bg-slate-800" href="#">💰 Bolsa de Puntos</a>
        <a class="block px-5 py-2.5 rounded-md hover:bg-slate-800" href="#">🎟️ Uso de Puntos</a>
      </nav>

      <div class="mt-auto border-t border-slate-700 px-5 py-4 flex items-center justify-between text-sm">
        <div>
          <p class="font-medium">Admin</p>
          <p class="text-slate-400">Administrador</p>
        </div>
        <span class="text-2xl">👤</span>
      </div>
    </aside>

    {{-- Contenido principal --}}
    <div class="flex-1 flex flex-col">
      <header class="bg-white shadow px-6 py-4 flex justify-between items-center">
        <div>
          <h1 class="text-xl font-semibold text-gray-800">@yield('title','Dashboard')</h1>
          <p class="text-sm text-gray-500">@yield('subtitle','Resumen del sistema de fidelización')</p>
        </div>
        <div class="flex items-center gap-4">
          <span class="text-gray-600 text-sm">Bienvenido, Admin</span>
          <button class="bg-slate-900 text-white text-sm px-3 py-1.5 rounded-lg hover:bg-slate-800">
            Cerrar sesión
          </button>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto min-h-0">
        @yield('content')
      </main>
    </div>
  </div>

</body>
</html>
