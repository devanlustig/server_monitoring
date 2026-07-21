<?php

use App\Http\Controllers\CpuDashboardController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/cpu', CpuDashboardController::class)->name('cpu.dashboard');
