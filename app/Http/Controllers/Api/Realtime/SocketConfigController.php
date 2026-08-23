<?php

namespace App\Http\Controllers\Api\Realtime;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Sprint 0 — Task 4 : Reverb / WebSocket discovery endpoint.
 *
 * @group Realtime — Socket Config
 */
class SocketConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $driver = (string) config('broadcasting.default');
        $cfg = (array) config("broadcasting.connections.{$driver}", []);

        return response()->json([
            'driver' => $driver,
            'key' => $cfg['key'] ?? null,
            'host' => $cfg['options']['host'] ?? null,
            'port' => isset($cfg['options']['port']) ? (int) $cfg['options']['port'] : null,
            'scheme' => $cfg['options']['scheme'] ?? 'https',
            'auth_endpoint' => '/api/broadcasting/auth',
        ]);
    }
}
