<?php

namespace App\Http\Controllers;

use App\Models\CpuMetric;
use Illuminate\View\View;

class CpuDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('cpu.dashboard', [
            'latestMetric' => CpuMetric::query()->latest('collected_at')->first(),
            'metrics' => CpuMetric::query()->latest('collected_at')->limit(30)->get(),
        ]);
    }
}
