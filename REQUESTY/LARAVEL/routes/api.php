<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\CajaApiController;
use App\Http\Controllers\Api\ClienteApiController;
use App\Http\Controllers\Api\MenuApiController;
use App\Http\Controllers\Api\OperacionApiController;
use App\Http\Controllers\Api\UsuarioApiController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthApiController::class, 'sesion']);
Route::get('/login', [AuthApiController::class, 'sesion'])->middleware('api.auth');
Route::post('/sesion', [AuthApiController::class, 'sesion']);
Route::get('/sesion', [AuthApiController::class, 'sesion'])->middleware('api.auth');
Route::match(['get', 'post'], '/logout', [AuthApiController::class, 'logout']);
Route::get('/roles', [AuthApiController::class, 'roles'])->middleware('api.auth');

Route::match(['get', 'post', 'put', 'patch', 'delete'], '/usuarios', [UsuarioApiController::class, 'usuarios'])
    ->middleware('api.auth:ADMIN');

Route::match(['get', 'post', 'put'], '/asistencia', [UsuarioApiController::class, 'asistencia'])
    ->middleware('api.auth:ADMIN,CAJERO,MESERO,COCINA');

Route::get('/actividad', [UsuarioApiController::class, 'actividad'])
    ->middleware('api.auth:ADMIN');

Route::match(['get', 'post'], '/clientes', [ClienteApiController::class, 'clientes'])
    ->middleware('api.auth:ADMIN,CAJERO,MESERO');

Route::match(['get', 'post', 'put', 'patch'], '/pqrs', [ClienteApiController::class, 'pqrs'])
    ->middleware('api.auth');

Route::match(['get', 'post', 'put', 'patch'], '/notificaciones', [ClienteApiController::class, 'notificaciones'])
    ->middleware('api.auth');

Route::match(['get', 'post', 'put', 'patch'], '/categorias', [MenuApiController::class, 'categorias'])
    ->middleware('api.auth');

Route::match(['get', 'post', 'put', 'patch', 'delete'], '/productos', [MenuApiController::class, 'productos'])
    ->middleware('api.auth');

Route::match(['get', 'post', 'put', 'patch'], '/inventario', [MenuApiController::class, 'inventario'])
    ->middleware('api.auth:ADMIN,CAJERO,MESERO,COCINA');

Route::match(['get', 'post', 'put', 'patch'], '/ingredientes', [MenuApiController::class, 'inventario'])
    ->middleware('api.auth:ADMIN,CAJERO,MESERO,COCINA');

Route::post('/mesas/crear', [OperacionApiController::class, 'crearMesa'])
    ->middleware('api.auth:ADMIN');

Route::match(['get', 'post', 'put'], '/mesas', [OperacionApiController::class, 'mesas'])
    ->middleware('api.auth');

Route::match(['get', 'post', 'put', 'patch', 'delete'], '/pedidos', [OperacionApiController::class, 'pedidos'])
    ->middleware('api.auth');

Route::match(['post', 'delete'], '/pedidos/descuento', [OperacionApiController::class, 'pedidoDescuento'])
    ->middleware('api.auth:ADMIN,CAJERO');

Route::get('/caja', [CajaApiController::class, 'caja'])
    ->middleware('api.auth:ADMIN,CAJERO');

Route::post('/caja/abrir', [CajaApiController::class, 'cajaAbrir'])
    ->middleware('api.auth:ADMIN,CAJERO');

Route::post('/caja/cerrar', [CajaApiController::class, 'cajaCerrar'])
    ->middleware('api.auth:ADMIN,CAJERO');

Route::match(['get', 'post', 'put', 'patch'], '/pagos', [CajaApiController::class, 'pagos'])
    ->middleware('api.auth:ADMIN,CAJERO');

Route::post('/propinas/distribuir', [CajaApiController::class, 'propinasDistribuir'])
    ->middleware('api.auth:ADMIN,CAJERO');

Route::match(['get', 'post', 'put', 'patch'], '/facturas', [CajaApiController::class, 'facturas'])
    ->middleware('api.auth:ADMIN,CAJERO,MESERO,CLIENTE');

Route::post('/facturas/anular', [CajaApiController::class, 'facturaAnular'])
    ->middleware('api.auth:ADMIN,CAJERO');

Route::match(['get', 'post'], '/descuentos', [CajaApiController::class, 'descuentos'])
    ->middleware('api.auth:ADMIN,CAJERO');

Route::match(['get', 'post'], '/reportes', [CajaApiController::class, 'reportes'])
    ->middleware('api.auth:ADMIN');
