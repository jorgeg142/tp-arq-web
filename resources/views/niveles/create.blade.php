@extends('layouts.app')
@section('title','Nuevo nivel de fidelización')
@section('subtitle','Define el rango de puntos y beneficios que tendrá el nivel')

@section('content')
<div class="px-6 py-6">
  <div class="bg-white rounded-xl shadow p-6 space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Crear nivel</h2>
        <p class="text-sm text-slate-500">Los rangos determinan cuándo un cliente asciende o desciende.</p>
      </div>
      <a href="{{ route('niveles.index') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50">⬅ Volver</a>
    </div>

    @include('niveles._form', [
      'action' => route('niveles.store'),
      'nivel' => null,
      'method' => null,
      'submit' => 'Crear nivel',
    ])
  </div>
</div>
@endsection
