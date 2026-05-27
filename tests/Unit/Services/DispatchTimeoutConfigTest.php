<?php

namespace Tests\Unit\Services;

use Tests\TestCase;

/**
 * 7.1 — config/dispatch.php defaults and per-trade overrides.
 *
 * These are pure config/unit tests — no DB, no HTTP.
 */
class DispatchTimeoutConfigTest extends TestCase
{
    public function test_default_timeout_is_15_seconds(): void
    {
        $this->assertSame(15, config('dispatch.default_timeout'));
    }

    public function test_nettoyage_uses_15_second_timeout(): void
    {
        $this->assertSame(15, config('dispatch.timeout_per_trade.nettoyage'));
    }

    public function test_toiturier_uses_30_second_timeout(): void
    {
        $this->assertSame(30, config('dispatch.timeout_per_trade.toiturier'));
    }

    public function test_plomberie_uses_30_second_timeout(): void
    {
        $this->assertSame(30, config('dispatch.timeout_per_trade.plomberie'));
    }

    public function test_unknown_trade_falls_back_to_default(): void
    {
        $slug    = 'trade_that_does_not_exist';
        $timeout = (int) config("dispatch.timeout_per_trade.{$slug}", config('dispatch.default_timeout'));
        $this->assertSame(config('dispatch.default_timeout'), $timeout);
    }

    public function test_max_escalation_depth_is_positive(): void
    {
        $this->assertGreaterThan(0, config('dispatch.max_escalation_depth'));
    }

    public function test_all_per_trade_timeouts_are_positive_integers(): void
    {
        foreach (config('dispatch.timeout_per_trade') as $slug => $timeout) {
            $this->assertIsInt($timeout, "Timeout for {$slug} must be int");
            $this->assertGreaterThan(0, $timeout, "Timeout for {$slug} must be > 0");
        }
    }
}
