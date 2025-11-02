@extends('layouts.app')
@section('title','Editar Regla')

@section('content')
<div class="px-6 py-6">
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Editar Regla</h2>
    <form method="post" action="{{ route('reglas.update', $r->id) }}" class="space-y-4">
      @csrf @method('PUT')
      @include('reglas._form', ['r'=>$r])
      <div class="flex gap-2">
        <button class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Actualizar</button>
        <a href="{{ route('reglas.index') }}" class="px-4 py-2 rounded border">Volver</a>
      </div>
    </form>
  </div>
</div>
@endsection
