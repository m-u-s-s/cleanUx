<?php

namespace Tests\Feature\FeatureFlag;

use App\Models\FeatureFlagOverride;
use App\Services\FeatureFlag\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * M18 — admin DB overrides must actually change runtime flag resolution (kill-switch).
 */
class FeatureFlagOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_override_disables_a_config_enabled_flag(): void
    {
        Config::set('features.surge_pricing', true);
        FeatureFlagOverride::create(['flag_key' => 'surge_pricing', 'is_enabled' => false]);

        $this->assertFalse(app(FeatureFlagService::class)->isEnabled('surge_pricing'));
    }

    public function test_override_enables_a_config_disabled_flag(): void
    {
        Config::set('features.experimental_x', false);
        FeatureFlagOverride::create(['flag_key' => 'experimental_x', 'is_enabled' => true]);

        $this->assertTrue(app(FeatureFlagService::class)->isEnabled('experimental_x'));
    }

    public function test_falls_back_to_config_when_no_override(): void
    {
        Config::set('features.surge_pricing', true);

        $this->assertTrue(app(FeatureFlagService::class)->isEnabled('surge_pricing'));
    }
}
