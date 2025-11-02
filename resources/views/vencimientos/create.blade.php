@extends('layouts.app')
@section('title','Nuevo Período')

@section('content')
<div class="px-6 py-6">
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Nuevo Período</h2>
    <form method="post" action="{{ route('vencimientos.store') }}" class="space-y-4">
      @csrf
      @include('vencimientos._form')
      <div class="flex gap-2">
        <button class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Guardar</button>
        <a href="{{ route('vencimientos.index') }}" class="px-4 py-2 rounded border">Cancelar</a>
      </div>
    </form>
  </div>
</div>
@endsection
