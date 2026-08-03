<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApplicationRequestLog;

class PerformanceMonitor
{
    /**
     * URL yang tidak perlu dicatat.
     */
    protected array $excluded = [

        '_debugbar',
        'telescope',
        'horizon',
        'storage',
        'build',
        'css',
        'js',
        'images',
        'favicon.ico',
    ];

    public function handle(
        Request $request,
        Closure $next
    ): Response {

        $start = microtime(true);

        $response = $next($request);

        $duration = round(
            (microtime(true) - $start) * 1000,
            2
        );

        logger()->info('Performance Path', [
            'path' => $request->path(),
            'skip' => $this->shouldSkip($request),
        ]);

        if (!$this->shouldSkip($request)) {

            $route = $request->route();

            logger()->info('Performance Monitor', [
                'url' => $request->path(),
                'response_time_ms' => $duration,
            ]);

            ApplicationRequestLog::create([

                'method' => $request->method(),

                'url' => $request->path(),

                'route_name' => optional($route)->getName(),

                'controller' => optional($route)
                    ?->getActionName(),

                'status_code' => $response->getStatusCode(),

                'response_time_ms' => $duration,

                'memory_usage_mb' => round(
                    memory_get_usage(true) / 1024 / 1024,
                    2
                ),

                'peak_memory_mb' => round(
                    memory_get_peak_usage(true) / 1024 / 1024,
                    2
                ),

                'ip_address' => $request->ip(),

                'user_id' => auth()->id(),

                'is_slow' => $duration >= 500,

                'created_at' => now(),

            ]);
        }

        return $response;
    }

    protected function shouldSkip(Request $request): bool
    {
        $path = $request->path();
        if (str_ends_with($path,'/refresh')) {
            return true;
        }
        foreach ($this->excluded as $exclude) {
            if (str_contains($path,$exclude)) {
                return true;
            }
        }
        return false;
    }
}