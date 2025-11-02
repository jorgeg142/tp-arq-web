@extends('layouts.app')
@section('title','Editar Período')

@section('content')
<div class="px-6 py-6">
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Editar Período</h2>
    <form method="post" action="{{ route('vencimientos.update', $p->id) }}" class="space-y-4">
      @csrf @method('PUT')
      @include('vencimientos._form', ['p'=>$p])
      <div class="flex gap-2">
        <button class="bg-slate-900 text-white px-4 py-2 rounded hover:bg-slate-800">Actualizar</button>
        <a href="{{ route('vencimientos.index') }}" class="px-4 py-2 rounded border">Volver</a>
      </div>
    </form>
  </div>
</div>
@endsection
