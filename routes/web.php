<?php

use App\Http\Controllers\CpuDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonitoredServerController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/cpu', CpuDashboardController::class)->name('cpu.dashboard');
Route::post('/servers/test-connection', [MonitoredServerController::class, 'test'])->name('servers.test-connection');
Route::resource('servers', MonitoredServerController::class);
