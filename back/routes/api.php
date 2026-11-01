<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [UserController::class, 'login']);
Route::get('/configuracion', [ConfiguracionController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::post('/logout', [UserController::class, 'logout']);
    Route::put('/cambiar-password', [UserController::class, 'changePassword']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::put('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
    Route::post('/users/{id}/avatar', [UserController::class, 'uploadAvatar']);
    Route::put('/users/{id}/permissions', [UserController::class, 'updateUserPermissions']);
    Route::get('/permissions', [UserController::class, 'permissions']);
    Route::get('/roles', [UserController::class, 'roles']);
    Route::post('/roles', [UserController::class, 'storeRole']);
    Route::put('/roles/{role}', [UserController::class, 'updateRole']);
    Route::delete('/roles/{role}', [UserController::class, 'destroyRole']);

    Route::get('/clientes', [ClienteController::class, 'index']);
    Route::post('/clientes', [ClienteController::class, 'store']);
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update']);
    Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy']);

    Route::get('/productos', [ProductoController::class, 'index']);
    Route::get('/productos-catalogos', [ProductoController::class, 'catalogos']);
    Route::post('/productos', [ProductoController::class, 'store']);
    Route::put('/productos/{producto}', [ProductoController::class, 'update']);
    Route::patch('/productos/{producto}/disponible', [ProductoController::class, 'toggleDisponible']);
    Route::post('/productos/{producto}/foto', [ProductoController::class, 'uploadPhoto']);
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy']);

    Route::get('/ventas', [VentaController::class, 'index']);
    Route::post('/ventas', [VentaController::class, 'store']);
    Route::get('/ventas-resumen', [VentaController::class, 'summary']);
    Route::get('/dashboard', [VentaController::class, 'dashboard']);
    Route::get('/ventas/{venta}', [VentaController::class, 'show']);
    Route::put('/ventas/{venta}/anular', [VentaController::class, 'cancel']);

    Route::get('/cocina', [VentaController::class, 'cocina']);
    Route::put('/ventas/{venta}/cocina', [VentaController::class, 'updateCocina']);

    Route::get('/reportes', [ReporteController::class, 'index']);
    Route::get('/reportes-exportar/excel', [ReporteController::class, 'exportExcel']);
    Route::get('/reportes-exportar/pdf', [ReporteController::class, 'exportPdf']);

    Route::put('/configuracion', [ConfiguracionController::class, 'update']);
    Route::post('/configuracion/logo', [ConfiguracionController::class, 'uploadLogo']);
});
