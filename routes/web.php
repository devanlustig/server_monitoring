<?php

use App\Http\Controllers\ApacheController;
use App\Http\Controllers\CpuDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonitoredServerController;
use App\Http\Controllers\PostgreSqlController;
use App\Http\Controllers\PostgreSqlIncidentController;
use App\Http\Controllers\NginxController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\GoogleAuthController;

Route::get('/login', [GoogleAuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('google.login')->middleware('guest');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback')->middleware('guest');
Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/cpu', CpuDashboardController::class)->name('cpu.dashboard');
    Route::post('/servers/test-connection', [MonitoredServerController::class, 'test'])->name('servers.test-connection');
    Route::get('/servers/{server}/refresh', [MonitoredServerController::class, 'refresh'])->name('servers.refresh');
    Route::resource('servers', MonitoredServerController::class);

    // Apache Routes
    Route::get('/servers/{server}/apache', [ApacheController::class, 'show'])->name('servers.apache');
    Route::get('/servers/{server}/nginx',[NginxController::class, 'show'])->name('servers.nginx');
    Route::get('/servers/{server}/apache/refresh', [ApacheController::class, 'refresh'])->name('servers.apache.refresh');
    Route::get('/servers/{server}/nginx/refresh', [NginxController::class, 'refresh'])->name('servers.nginx.refresh');
    Route::get('/servers/{server}/nginx/history', [NginxController::class, 'history'])->name('servers.nginx.history');

    // PostgreSQL Routes
    Route::get('/servers/{server}/postgresql', [PostgreSqlController::class, 'show'])->name('servers.postgresql');
    Route::post('/servers/{server}/postgresql/{pid}/terminate', [PostgreSqlController::class, 'terminate'])->name('servers.postgresql.terminate');
    Route::post('/servers/{server}/postgresql/kill-idle', [PostgreSqlController::class, 'killIdle'])->name('servers.postgresql.killIdle');
    Route::post('/servers/{server}/postgresql/kill-idle-older', [PostgreSqlController::class, 'killIdleOlder'])->name('servers.postgresql.killIdleOlder');
    Route::post('/servers/{server}/postgresql/kill-selected', [PostgreSqlController::class, 'killSelected'])->name('servers.postgresql.killSelected');
    Route::post('/servers/{server}/postgresql/capture', [PostgreSqlIncidentController::class, 'capture'])->name('servers.postgresql.capture');
    Route::post('/servers/{server}/postgresql/restart', [PostgreSqlController::class, 'restart'])->name('servers.postgresql.restart');
    Route::get('/servers/{server}/status', [MonitoredServerController::class, 'status'])->name('servers.status');
    Route::get('/servers/{server}/cpu-analysis', [\App\Http\Controllers\Api\CpuAnalysisController::class, 'analyze'])->name('servers.cpu-analysis');

    Route::get('/servers/{server}/apache/history',[ApacheController::class, 'history'])->name('servers.apache.history');
    Route::get('/servers/{server}/apache/request-analysis', [ApacheController::class, 'requestAnalysis'])->name('servers.apache.request-analysis');
    Route::get('/servers/{server}/nginx/request-analysis', [NginxController::class, 'requestAnalysis'])->name('servers.nginx.request-analysis');

    // Disk Storage Growth Detail Routes
    Route::get('/servers/{server}/disk/growth-detail', [\App\Http\Controllers\DiskGrowthDetailController::class, 'getDirectories'])->name('servers.disk.growth-detail');
    Route::get('/servers/{server}/disk/growth-detail/files', [\App\Http\Controllers\DiskGrowthDetailController::class, 'getFiles'])->name('servers.disk.growth-detail-files');
});