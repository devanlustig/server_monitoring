<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitoredServer;
use App\Services\Monitoring\CpuCorrelationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CpuAnalysisController extends Controller
{
    public function __construct(
        private readonly CpuCorrelationEngine $engine
    ) {}

    public function analyze(MonitoredServer $server, Request $request): JsonResponse
    {
        $timestamp = $request->get('timestamp');
        if (!$timestamp) {
            // Default to the latest recorded CPU metric timestamp
            $latest = $server->cpuMetrics()->latest('collected_at')->first();
            $timestamp = $latest ? $latest->collected_at->toDateTimeString() : now()->toDateTimeString();
        }

        $result = $this->engine->analyze($server, $timestamp);

        return response()->json($result);
    }
}
