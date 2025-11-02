<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\ConceptosUsoController;
use App\Http\Controllers\ReglasAsignacionController;
use App\Http\Controllers\VencimientosController;
use App\Http\Controllers\BolsasController;
use App\Http\Controllers\UsosController;

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

Route::get('/bolsa-puntos', [BolsasController::class, 'index'])->name('bolsas.index');
Route::get('/bolsa-puntos/export', [BolsasController::class, 'export'])->name('bolsas.export');
// Show JSON para el modal (evitamos chocar con edit)
Route::get('/bolsa-puntos/{id}/detalle', [BolsasController::class, 'show'])->name('bolsas.show');
// CRUD
Route::get('/bolsa-puntos/create', [BolsasController::class, 'create'])->name('bolsas.create');
Route::post('/bolsa-puntos',        [BolsasController::class, 'store'])->name('bolsas.store');
Route::get('/bolsa-puntos/{id}/edit', [BolsasController::class, 'edit'])->name('bolsas.edit');
Route::put('/bolsa-puntos/{id}',      [BolsasController::class, 'update'])->name('bolsas.update');
Route::delete('/bolsa-puntos/{id}',   [BolsasController::class, 'destroy'])->name('bolsas.destroy');

Route::get('/usos',            [UsosController::class, 'index'])->name('usos.index');
Route::get('/usos/create',     [UsosController::class, 'create'])->name('usos.create');
Route::post('/usos',           [UsosController::class, 'store'])->name('usos.store');
Route::get('/usos/{id}',       [UsosController::class, 'show'])->name('usos.show'); // JSON para modal
Route::get('/usos/{id}/edit',  [UsosController::class, 'edit'])->name('usos.edit');
Route::put('/usos/{id}',       [UsosController::class, 'update'])->name('usos.update');
Route::delete('/usos/{id}',    [UsosController::class, 'destroy'])->name('usos.destroy');
