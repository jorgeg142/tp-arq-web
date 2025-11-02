<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\ConceptosUsoController;
use App\Http\Controllers\ReglasAsignacionController;
use App\Http\Controllers\VencimientosController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::resource('clientes', ClientesController::class);
Route::resource('conceptos-uso', ConceptosUsoController::class)
     ->names('conceptos');
Route::resource('reglas-asignacion', ReglasAsignacionController::class)
     ->names('reglas'); 
Route::resource('vencimientos', VencimientosController::class)
     ->names('vencimientos'); 