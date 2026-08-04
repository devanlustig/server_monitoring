<?php
use App\Http\Controllers\Api\ApplicationPerformanceController;

Route::post(
    '/performance',
    [ApplicationPerformanceController::class,'store']
);
?>