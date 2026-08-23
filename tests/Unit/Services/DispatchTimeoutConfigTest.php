<?php

namespace Tests\Unit\Services;

use Tests\TestCase;

/** 7.1 — config/dispatch.php defaults and per-trade overrides. */
class DispatchTimeoutConfigTest extends TestCase
{
    /** VINGT SECONDES, et c'est un choix produit. */
    public function test_default_timeout_is_20_seconds(): void
    {
        $this->assertSame(20, config('dispatch.default_timeout'));
    }

    public function test_nettoyage_uses_20_second_timeout(): void
    {
        $this->assertSame(20, config('dispatch.timeout_per_trade.nettoyage'));
    }

    /** Les vagues, l'échéance globale et la fraîcheur de position sont EN CONFIG. */
    public function test_les_reglages_du_moteur_sont_tous_en_config(): void
    {
        $this->assertGreaterThan(0, config('dispatch.waves.initial_radius_m'));
        $this->assertGreaterThan(0, config('dispatch.waves.step_m'));
        $this->assertGreaterThanOrEqual(
            config('dispatch.waves.initial_radius_m'),
            config('dispatch.waves.max_radius_m'),
        );
        $this->assertGreaterThan(0, config('dispatch.search_deadline_seconds'));
        $this->assertGreaterThan(0, config('dispatch.position_freshness_minutes'));
        $this->assertGreaterThan(0, config('dispatch.broadcast_max_candidates'));
        $this->assertGreaterThan(0, config('dispatch.scheduled_offer_timeout_seconds'));
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
        $slug = 'trade_that_does_not_exist';
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
