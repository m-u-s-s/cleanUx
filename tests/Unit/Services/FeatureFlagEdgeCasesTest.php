<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\FeatureFlag\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Additional edge-case coverage for FeatureFlagService, the feature() helper, and the @feature Blade directive. */
class FeatureFlagEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private FeatureFlagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FeatureFlagService;
    }

    // ─────────────────────────────────────────────────────────────────
    // Unknown flags (no config entry at all)
    // ─────────────────────────────────────────────────────────────────

    public function test_completely_absent_flag_returns_false_when_features_config_empty(): void
    {
        config(['features' => []]);

        $this->assertFalse($this->service->isEnabled('totally_unknown_flag'));
    }

    public function test_completely_absent_flag_returns_false_when_features_config_has_unrelated_entries(): void
    {
        config(['features' => ['chat_v2' => true, 'beta' => false]]);

        $this->assertFalse($this->service->isEnabled('not_configured_at_all'));
    }

    public function test_completely_absent_flag_returns_false_with_null_user(): void
    {
        config(['features' => ['other_flag' => true]]);

        $this->assertFalse($this->service->isEnabled('ghost_flag', null));
    }

    // ─────────────────────────────────────────────────────────────────
    // Percentage rollout determinism
    // ─────────────────────────────────────────────────────────────────

    public function test_percentage_rollout_is_deterministic_across_ten_calls(): void
    {
        config(['features' => ['stable_rollout' => ['percentage' => 50]]]);

        $user = User::factory()->create();
        $first = $this->service->isEnabled('stable_rollout', $user);

        // Dix tirages releves puis compares d'un coup : si le resultat oscille, on veut savoir
        // COMBIEN de fois et a quels rangs — un tirage instable se reconnait a son motif.
        $tirages = [];

        for ($i = 0; $i < 10; $i++) {
            $tirages[] = $this->service->isEnabled('stable_rollout', $user);
        }

        $this->assertSame(array_fill(0, 10, $first), $tirages, 'Le deploiement progressif doit etre deterministe pour un meme compte.');
    }

    public function test_different_flags_can_produce_different_outcomes_for_same_user(): void
    {
        // Two flags at 50% with the same user may differ — just verify no exception is thrown
        config(['features' => [
            'flag_alpha' => ['percentage' => 50],
            'flag_beta' => ['percentage' => 50],
        ]]);

        $user = User::factory()->create();

        $resultAlpha = $this->service->isEnabled('flag_alpha', $user);
        $resultBeta = $this->service->isEnabled('flag_beta', $user);

        $this->assertIsBool($resultAlpha);
        $this->assertIsBool($resultBeta);
    }

    public function test_percentage_rollout_different_users_can_diverge_at_50_percent(): void
    {
        config(['features' => ['diverge_flag' => ['percentage' => 50]]]);

        // With enough users at 50%, we expect at least one enabled and one disabled.
        $users = User::factory()->count(20)->create();
        $results = $users->map(fn ($u) => $this->service->isEnabled('diverge_flag', $u));

        $this->assertContains(true, $results->all(), 'Expected at least one enabled user at 50%');
        $this->assertContains(false, $results->all(), 'Expected at least one disabled user at 50%');
    }

    // ─────────────────────────────────────────────────────────────────
    // @feature Blade directive registration
    // ─────────────────────────────────────────────────────────────────

    public function test_blade_feature_directive_is_registered(): void
    {
        $customDirectives = Blade::getCustomDirectives();

        $this->assertArrayHasKey('feature', $customDirectives,
            '@feature directive must be registered in AppServiceProvider');
    }

    public function test_blade_endfeature_directive_is_registered(): void
    {
        $customDirectives = Blade::getCustomDirectives();

        $this->assertArrayHasKey('endfeature', $customDirectives,
            '@endfeature directive must be registered alongside @feature');
    }

    public function test_blade_feature_directive_compiles_to_valid_php(): void
    {
        $compiled = Blade::compileString("@feature('blade_test')CONTENT@endfeature");

        $this->assertStringContainsString('<?php', $compiled);
        $this->assertStringContainsString('endif', $compiled);
        $this->assertStringContainsString('blade_test', $compiled);
    }
}
