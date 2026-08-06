<?php

use App\Http\Controllers\ApacheController;
use App\Http\Controllers\CpuDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonitoredServerController;
use App\Http\Controllers\PostgreSqlController;
use App\Http\Controllers\PostgreSqlIncidentController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/cpu', CpuDashboardController::class)->name('cpu.dashboard');
Route::post('/servers/test-connection', [MonitoredServerController::class, 'test'])->name('servers.test-connection');
Route::get('/servers/{server}/refresh', [MonitoredServerController::class, 'refresh'])->name('servers.refresh');
Route::resource('servers', MonitoredServerController::class);

// Apache Routes
Route::get('/servers/{server}/apache', [ApacheController::class, 'show'])->name('servers.apache');
Route::get('/servers/{server}/apache/refresh', [ApacheController::class, 'refresh'])->name('servers.apache.refresh');

// PostgreSQL Routes
Route::get('/servers/{server}/postgresql', [PostgreSqlController::class, 'show'])->name('servers.postgresql');
Route::post('/servers/{server}/postgresql/{pid}/terminate', [PostgreSqlController::class, 'terminate'])->name('servers.postgresql.terminate');
Route::post('/servers/{server}/postgresql/kill-idle', [PostgreSqlController::class, 'killIdle'])->name('servers.postgresql.killIdle');
Route::post('/servers/{server}/postgresql/kill-idle-older', [PostgreSqlController::class, 'killIdleOlder'])->name('servers.postgresql.killIdleOlder');
Route::post('/servers/{server}/postgresql/kill-selected', [PostgreSqlController::class, 'killSelected'])->name('servers.postgresql.killSelected');
Route::post('/servers/{server}/postgresql/capture', [PostgreSqlIncidentController::class, 'capture'])->name('servers.postgresql.capture');
Route::post('/servers/{server}/postgresql/restart', [PostgreSqlController::class, 'restart'])->name('servers.postgresql.restart');
Route::get('/servers/{server}/status', [MonitoredServerController::class, 'status'])->name('servers.status');

Route::get('/servers/{server}/apache/history',[ApacheController::class, 'history'])->name('servers.apache.history');