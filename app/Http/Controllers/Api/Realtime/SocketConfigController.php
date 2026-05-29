<?php

namespace App\Http\Controllers\Api\Realtime;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @group Realtime — Socket Config
 *
 * Sprint 0 — Task 4 : Reverb / WebSocket discovery endpoint.
 *
 * Returns the public WebSocket connection parameters so that the React-Native
 * mobile client can bootstrap its Pusher-JS connection without hard-coding
 * host, port, key, or scheme. The secret is intentionally never included.
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
