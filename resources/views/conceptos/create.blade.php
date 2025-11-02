@extends('layouts.app')
@section('title','Nuevo Concepto')

@section('content')
<div class="px-6 py-6 space-y-4">
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Nuevo Concepto</h2>
    <form method="post" action="{{ route('conceptos.store') }}" class="space-y-4">
      @csrf
      @include('conceptos._form')
      <div class="flex gap-2">
        <button class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Guardar</button>
        <a href="{{ route('conceptos.index') }}" class="px-4 py-2 rounded border">Cancelar</a>
      </div>
    </form>
  </div>
</div>
@endsection
