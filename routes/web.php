<?php

use App\Http\Controllers\CpuDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonitoredServerController;
use App\Http\Controllers\PostgreSqlController;
use Illuminate\Support\Facades\Route;
use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\CpuCollector;


Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/cpu', CpuDashboardController::class)->name('cpu.dashboard');
Route::post('/servers/test-connection', [MonitoredServerController::class, 'test'])->name('servers.test-connection');
Route::resource('servers', MonitoredServerController::class);
Route::get('/servers/{server}/postgresql',[PostgreSqlController::class, 'show'])->name('servers.postgresql');
Route::post('/servers/{server}/postgresql/{pid}/terminate',[PostgreSqlController::class,'terminate'])->name('servers.postgresql.terminate');
Route::post('/servers/{server}/postgresql/kill-idle',[PostgreSqlController::class,'killIdle'])->name('servers.postgresql.killIdle');
Route::post('/servers/{server}/postgresql/kill-idle-older',[PostgreSqlController::class,'killIdleOlder'])->name('servers.postgresql.killIdleOlder');
Route::post('/servers/{server}/postgresql/kill-selected',[PostgreSqlController::class,'killSelected'])->name('servers.postgresql.killSelected');
