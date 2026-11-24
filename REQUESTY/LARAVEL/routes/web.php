<?php

use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\ModuloWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthWebController::class, 'index']);
Route::match(['get', 'post'], '/login', [AuthWebController::class, 'login'])->name('login');
Route::match(['get', 'post'], '/registro', [AuthWebController::class, 'registro'])->name('registro');
Route::get('/logout', [AuthWebController::class, 'logout'])->name('logout');

Route::get('/pedidos', [ModuloWebController::class, 'pedidos'])
    ->middleware('web.auth:ADMIN,CAJERO,MESERO,COCINA')
    ->name('pedidos');

Route::get('/cocina', [ModuloWebController::class, 'cocina'])
    ->middleware('web.auth:ADMIN,CAJERO,MESERO,COCINA')
    ->name('cocina');

Route::get('/menu', [ModuloWebController::class, 'menu'])
    ->middleware('web.auth')
    ->name('menu');

Route::get('/caja', [ModuloWebController::class, 'caja'])
    ->middleware('web.auth:ADMIN,CAJERO,CLIENTE')
    ->name('caja');

Route::get('/mesas', [ModuloWebController::class, 'mesas'])
    ->middleware('web.auth:ADMIN,CAJERO,MESERO')
    ->name('mesas');

Route::get('/reportes', [ModuloWebController::class, 'reportes'])
    ->middleware('web.auth:ADMIN')
    ->name('reportes');

Route::get('/cuentas', [ModuloWebController::class, 'cuentas'])
    ->middleware('web.auth:ADMIN')
    ->name('cuentas');

Route::get('/pqrs', [ModuloWebController::class, 'pqrs'])
    ->middleware('web.auth')
    ->name('pqrs');
