<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\FeatureFlag\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeatureFlagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FeatureFlagService;
    }

    public function test_returns_false_when_flag_not_in_config(): void
    {
        config(['features' => []]);

        $this->assertFalse($this->service->isEnabled('unknown_flag'));
    }

    public function test_returns_true_for_bool_true_flag(): void
    {
        config(['features' => ['chat_v2' => true]]);

        $this->assertTrue($this->service->isEnabled('chat_v2'));
    }

    public function test_returns_false_for_bool_false_flag(): void
    {
        config(['features' => ['surge_pricing' => false]]);

        $this->assertFalse($this->service->isEnabled('surge_pricing'));
    }

    public function test_returns_false_for_unknown_flag_when_config_has_other_flags(): void
    {
        config(['features' => ['chat_v2' => true]]);

        $this->assertFalse($this->service->isEnabled('does_not_exist'));
    }

    public function test_percentage_rollout_is_deterministic(): void
    {
        config(['features' => ['beta_feature' => ['percentage' => 50]]]);

        $user = User::factory()->create();
        $firstResult = $this->service->isEnabled('beta_feature', $user);

        // Same user must always get the same result
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($firstResult, $this->service->isEnabled('beta_feature', $user));
        }
    }

    public function test_percentage_100_enables_for_all_users(): void
    {
        config(['features' => ['full_rollout' => ['percentage' => 100]]]);

        $users = User::factory()->count(5)->create();

        foreach ($users as $user) {
            $this->assertTrue($this->service->isEnabled('full_rollout', $user));
        }
    }

    public function test_percentage_0_disables_for_all_users(): void
    {
        config(['features' => ['zero_rollout' => ['percentage' => 0]]]);

        $users = User::factory()->count(5)->create();

        foreach ($users as $user) {
            $this->assertFalse($this->service->isEnabled('zero_rollout', $user));
        }
    }

    public function test_user_list_enables_only_listed_users(): void
    {
        $allowed = User::factory()->create();
        $blocked = User::factory()->create();

        config(['features' => ['invite_only' => ['users' => [$allowed->id]]]]);

        $this->assertTrue($this->service->isEnabled('invite_only', $allowed));
        $this->assertFalse($this->service->isEnabled('invite_only', $blocked));
    }

    public function test_role_based_flag_matches_user_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);

        config(['features' => ['admin_only' => ['roles' => ['admin']]]]);

        $this->assertTrue($this->service->isEnabled('admin_only', $admin));
        $this->assertFalse($this->service->isEnabled('admin_only', $client));
    }

    public function test_enabled_false_kill_switch_overrides_everything(): void
    {
        $user = User::factory()->create();

        config(['features' => ['dead_feature' => ['enabled' => false, 'percentage' => 100]]]);

        $this->assertFalse($this->service->isEnabled('dead_feature', $user));
    }

    public function test_returns_false_without_user_for_user_dependent_rules(): void
    {
        config(['features' => [
            'pct_flag' => ['percentage' => 100],
            'user_flag' => ['users' => [1, 2]],
            'role_flag' => ['roles' => ['admin']],
        ]]);

        $this->assertFalse($this->service->isEnabled('pct_flag'));
        $this->assertFalse($this->service->isEnabled('user_flag'));
        $this->assertFalse($this->service->isEnabled('role_flag'));
    }
}
