<?php

namespace App\Http\Controllers;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Memory\MemoryDetailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class MemoryDetailController extends Controller
{
    public function __construct(
        private readonly MemoryDetailService $memoryDetailService
    ) {}

    public function show(Request $request, MonitoredServer $server): JsonResponse
    {
        try {
            $data = $this->memoryDetailService->getMemoryDetail($server);

            if (!$data['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $data['message']
                ], 500); // Wait, requirement says "tidak menyebabkan HTTP 500". So maybe 200 with success = false.
            }

            return response()->json($data);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while fetching memory details: ' . $e->getMessage()
            ]);
        }
    }
}
