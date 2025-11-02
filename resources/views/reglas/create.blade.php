@extends('layouts.app')
@section('title','Nueva Regla')

@section('content')
<div class="px-6 py-6">
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Nueva Regla</h2>
    <form method="post" action="{{ route('reglas.store') }}" class="space-y-4">
      @csrf
      @include('reglas._form')
      <div class="flex gap-2">
        <button class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Guardar</button>
        <a href="{{ route('reglas.index') }}" class="px-4 py-2 rounded border">Cancelar</a>
      </div>
    </form>
  </div>
</div>
@endsection
