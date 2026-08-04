<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApplicationPerformanceLog;
use Illuminate\Http\Request;

class ApplicationPerformanceController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([

            'application'=>'required|string|max:100',
            'environment'=>'nullable|string',
            'server_name'=>'nullable|string',
            'method'=>'required|string',
            'endpoint'=>'required|string',
            'route_name'=>'nullable|string',
            'status_code'=>'required|integer',
            'response_time_ms'=>'required|numeric',
            'memory_usage_mb'=>'nullable|numeric',
            'peak_memory_mb'=>'nullable|numeric',
            'ip_address'=>'nullable|string',
            'request_id'=>'nullable|string',
            'extra'=>'nullable|array',
            'requested_at'=>'required|date',

        ]);

        ApplicationPerformanceLog::create($validated);

        return response()->json([
            'success'=>true
        ]);
    }
}