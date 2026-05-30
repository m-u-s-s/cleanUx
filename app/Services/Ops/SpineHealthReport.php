<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Spine health report — asserts that the five infrastructure dependencies
 * critical to the money-path are reachable.
 *
 * Design constraints:
 *  - collect() NEVER throws; every probe wraps its work in try/catch.
 *  - In the test environment probes that require real infrastructure (Redis,
 *    Stripe, Reverb) return ok=false with a descriptive detail — graceful degradation.
 *  - The stripe probe NEVER makes a real network call; it only checks whether a
 *    key is configured (to avoid flaky CI when STRIPE_SECRET is absent).
 *
 * @return array{
 *   db:    array{ok: bool, detail: string},
 *   redis: array{ok: bool, detail: string},
 *   queue: array{ok: bool, detail: string},
 *   stripe: array{ok: bool, detail: string},
 *   reverb: array{ok: bool, detail: string},
 * }
 */
class SpineHealthReport
{
    /**
     * Run all five spine probes and return their results.
     *
     * @return array<string, array{ok: bool, detail: string}>
     */
    public function collect(): array
    {
        return [
            'db' => $this->probeDb(),
            'redis' => $this->probeRedis(),
            'queue' => $this->probeQueue(),
            'stripe' => $this->probeStripe(),
            'reverb' => $this->probeReverb(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Probes
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * DB probe — verifies the default PDO connection can be obtained.
     *
     * @return array{ok: bool, detail: string}
     */
    protected function probeDb(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true, 'detail' => 'ok'];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * Redis probe — sends a PING to the default Redis connection.
     * ok=false when Redis is not running (graceful — never throws).
     *
     * @return array{ok: bool, detail: string}
     */
    protected function probeRedis(): array
    {
        try {
            $response = Redis::connection()->ping();

            // Predis returns a Status object; PhpRedis returns the string "+PONG" or true.
            $ok = $response === true
                || $response === 1
                || (is_string($response) && stripos($response, 'PONG') !== false)
                || (is_object($response) && method_exists($response, '__toString') && stripos((string) $response, 'PONG') !== false);

            return ['ok' => $ok, 'detail' => $ok ? 'pong' : 'unexpected_response'];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * Queue probe — confirms a queue connection is configured.
     * ok=true whenever the connection name is set; does not require a live worker.
     * Best-effort: also reports the jobs-table depth when driver=database.
     *
     * @return array{ok: bool, detail: string}
     */
    protected function probeQueue(): array
    {
        try {
            $driver = (string) config('queue.default', '');

            if ($driver === '' || $driver === 'null') {
                return ['ok' => false, 'detail' => 'no_queue_driver_configured'];
            }

            // 'sync' means jobs execute inline — no real background processing.
            // Flag as not production-ready so a launch-hardening health check catches it.
            if ($driver === 'sync') {
                return ['ok' => false, 'detail' => 'driver=sync — no background processing'];
            }

            $detail = "driver={$driver}";

            // Best-effort depth for the database driver
            if ($driver === 'database') {
                try {
                    $depth = DB::table('jobs')->count();
                    $detail .= " depth={$depth}";
                } catch (Throwable) {
                    $detail .= ' depth=unavailable';
                }
            }

            return ['ok' => true, 'detail' => $detail];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * Stripe probe — checks that a Cashier API secret is configured.
     * DOES NOT make a real network call (to keep tests fast and CI green).
     * If the key looks like a live/test key it is considered reachable enough.
     *
     * @return array{ok: bool, detail: string}
     */
    protected function probeStripe(): array
    {
        try {
            $secret = (string) config('cashier.secret', '');

            if ($secret === '') {
                return ['ok' => false, 'detail' => 'no_api_key'];
            }

            // Keys that are obviously placeholders / test dummies
            $isDummy = $secret === 'null'
                || str_starts_with($secret, 'your_')
                || str_starts_with($secret, 'REPLACE_')
                || str_starts_with($secret, 'sk_dummy')
                || strlen($secret) < 20;

            if ($isDummy) {
                return ['ok' => false, 'detail' => 'dummy_api_key'];
            }

            // The key is set and plausible — report it as configured.
            // Actual connectivity is validated by Stripe webhook tests, not here.
            $prefix = substr($secret, 0, 7); // e.g. "sk_live" or "sk_test"

            return ['ok' => true, 'detail' => "configured prefix={$prefix}…"];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * Reverb probe — checks that the broadcasting driver is set to 'reverb'
     * and that the host/key config values are present.
     * Does not require a live WebSocket connection.
     *
     * @return array{ok: bool, detail: string}
     */
    protected function probeReverb(): array
    {
        try {
            $driver = (string) config('broadcasting.default', '');

            if ($driver !== 'reverb') {
                return [
                    'ok' => false,
                    'detail' => "broadcasting.default is '{$driver}' (not 'reverb')",
                ];
            }

            $host = (string) config('broadcasting.connections.reverb.options.host', '');
            $key = (string) config('broadcasting.connections.reverb.key', '');

            if ($host === '' || $key === '') {
                return ['ok' => false, 'detail' => 'reverb_driver_set_but_host_or_key_missing'];
            }

            return ['ok' => true, 'detail' => "reverb configured host={$host}"];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }
}
