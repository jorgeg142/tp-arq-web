<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UsosApiController;
use App\Http\Controllers\Api\VencimientosApiController;
use App\Http\Controllers\Api\ReglasAsignacionApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\ConceptosUsoApiController;
use App\Http\Controllers\Api\ClientesApiController;
use App\Http\Controllers\Api\BolsasApiController;

Route::prefix('usos')->group(function () {
    Route::get('/',           [UsosApiController::class, 'index']);
    Route::post('/',          [UsosApiController::class, 'store']);
    Route::get('{id}',        [UsosApiController::class, 'show']);
    Route::put('{id}',        [UsosApiController::class, 'update']);
    Route::delete('{id}',     [UsosApiController::class, 'destroy']);

    // utilitario
    Route::get('saldo/{clienteId}', [UsosApiController::class, 'saldoCliente']);
});

Route::prefix('vencimientos')->group(function () {
    Route::get('/',        [VencimientosApiController::class, 'index']);
    Route::post('/',       [VencimientosApiController::class, 'store']);
    Route::get('{id}',     [VencimientosApiController::class, 'show']);
    Route::put('{id}',     [VencimientosApiController::class, 'update']);
    Route::delete('{id}',  [VencimientosApiController::class, 'destroy']);
});

Route::prefix('reglas')->group(function () {
    Route::get('/',          [ReglasAsignacionApiController::class, 'index']);
    Route::post('/',         [ReglasAsignacionApiController::class, 'store']);
    Route::get('{id}',       [ReglasAsignacionApiController::class, 'show']);
    Route::put('{id}',       [ReglasAsignacionApiController::class, 'update']);
    Route::delete('{id}',    [ReglasAsignacionApiController::class, 'destroy']);

    // utilitario para validar solapamientos desde el front
    Route::get('overlaps/check', [ReglasAsignacionApiController::class, 'overlaps']);
});

Route::get('dashboard', [DashboardApiController::class, 'summary']);
// proteger con auth:sanctum si corresponde.

Route::prefix('conceptos')->group(function () {
    Route::get('/',           [ConceptosUsoApiController::class, 'index']);
    Route::post('/',          [ConceptosUsoApiController::class, 'store']);
    Route::get('{id}',        [ConceptosUsoApiController::class, 'show']);
    Route::put('{id}',        [ConceptosUsoApiController::class, 'update']);
    Route::delete('{id}',     [ConceptosUsoApiController::class, 'destroy']);

    // utilitario
    Route::get('categorias/list', [ConceptosUsoApiController::class, 'categorias']);
});

Route::prefix('clientes')->group(function () {
    Route::get('/',        [ClientesApiController::class, 'index']);
    Route::post('/',       [ClientesApiController::class, 'store']);
    Route::get('{id}',     [ClientesApiController::class, 'show']);
    Route::put('{id}',     [ClientesApiController::class, 'update']);
    Route::delete('{id}',  [ClientesApiController::class, 'destroy']);
});
// auth: envolver con ->middleware('auth:sanctum')

Route::prefix('bolsas')->group(function () {
    Route::get('/',        [BolsasApiController::class, 'index']);
    Route::post('/',       [BolsasApiController::class, 'store']);
    Route::get('kpis',     [BolsasApiController::class, 'kpis']);
    Route::get('trend',    [BolsasApiController::class, 'trend']);
    Route::get('top',      [BolsasApiController::class, 'top']);
    Route::get('export',   [BolsasApiController::class, 'export']);
    Route::get('{id}',     [BolsasApiController::class, 'show']);
    Route::put('{id}',     [BolsasApiController::class, 'update']);
    Route::delete('{id}',  [BolsasApiController::class, 'destroy']);
});
// auth: ->middleware('auth:sanctum')
