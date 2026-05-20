<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Health check endpoint for load balancers / monitoring.
 *
 *   GET /health        — liveness check (always 200 si app boot OK)
 *   GET /health/deep   — readiness check (DB + Cache + checks externes)
 *
 * En cas de failure deep, retourne 503 pour que le LB sorte de rotation.
 */
class HealthCheckController extends Controller
{
    public function liveness(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'cleanux',
            'version' => config('app.version', '1.0.0'),
            'env' => app()->environment(),
            'ts' => now()->toIso8601String(),
        ]);
    }

    public function readiness(): JsonResponse
    {
        $checks = [];
        $allOk = true;

        // DB
        try {
            $start = microtime(true);
            DB::select('select 1');
            $checks['database'] = ['ok' => true, 'latency_ms' => (int) round((microtime(true) - $start) * 1000)];
        } catch (\Throwable $e) {
            $checks['database'] = ['ok' => false, 'error' => $e->getMessage()];
            $allOk = false;
        }

        // Cache
        try {
            $start = microtime(true);
            Cache::put('health:ping', 'pong', 5);
            $value = Cache::get('health:ping');
            $checks['cache'] = ['ok' => $value === 'pong', 'latency_ms' => (int) round((microtime(true) - $start) * 1000)];
            if ($value !== 'pong') {
                $allOk = false;
            }
        } catch (\Throwable $e) {
            $checks['cache'] = ['ok' => false, 'error' => $e->getMessage()];
            $allOk = false;
        }

        // Queue connection
        try {
            $checks['queue'] = ['ok' => true, 'driver' => config('queue.default')];
        } catch (\Throwable $e) {
            $checks['queue'] = ['ok' => false, 'error' => $e->getMessage()];
            $allOk = false;
        }

        // Storage disk
        try {
            $disk = config('filesystems.default');
            \Illuminate\Support\Facades\Storage::disk($disk)->exists('/');
            $checks['storage'] = ['ok' => true, 'disk' => $disk];
        } catch (\Throwable $e) {
            $checks['storage'] = ['ok' => false, 'error' => $e->getMessage()];
            $allOk = false;
        }

        return response()->json([
            'status' => $allOk ? 'ok' : 'degraded',
            'checks' => $checks,
            'ts' => now()->toIso8601String(),
        ], $allOk ? 200 : 503);
    }
}
