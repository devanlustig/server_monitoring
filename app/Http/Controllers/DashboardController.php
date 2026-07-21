<?php

namespace App\Http\Controllers;

use App\Models\MonitoredServer;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Display the initial monitoring overview. */
    public function __invoke(): View
    {
        return view('dashboard', [
            'serverCount' => MonitoredServer::query()->count(),
            'recentServers' => MonitoredServer::query()->latest()->limit(5)->get(),
        ]);
    }
}
