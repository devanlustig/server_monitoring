<?php

namespace App\Http\Controllers;

use App\Models\MonitoredServer;
use App\Services\Monitoring\DiskGrowthDetailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DiskGrowthDetailController extends Controller
{
    public function __construct(
        private readonly DiskGrowthDetailService $growthDetailService
    ) {}

    public function getDirectories(Request $request, MonitoredServer $server): JsonResponse
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        try {
            $data = $this->growthDetailService->getDirectoryGrowth($server, $date);
            return response()->json($data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to fetch directory growth detail: ' . $e->getMessage()], 500);
        }
    }

    public function getFiles(Request $request, MonitoredServer $server): JsonResponse
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $directory = $request->input('directory');

        if (empty($directory)) {
            return response()->json(['error' => 'Directory parameter is required'], 422);
        }

        try {
            $data = $this->growthDetailService->getFileGrowth($server, $date, $directory);
            return response()->json($data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to fetch file growth detail: ' . $e->getMessage()], 500);
        }
    }
}
