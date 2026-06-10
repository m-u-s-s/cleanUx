<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\FeatureFlagsManager;
use App\Models\FeatureFlagOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FeatureFlagsManagerCoverageBatch9Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->client = User::factory()->client()->create();
    }

    public function test_mount_renders_flags_from_config(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FeatureFlagsManager::class)
            ->assertOk()
            ->assertViewHas('flags');
    }

    public function test_toggle_flag_creates_override_disabling_config_true_flag(): void
    {
        // surge_pricing is true in config; first toggle should write an override = false.
        Livewire::actingAs($this->admin)
            ->test(FeatureFlagsManager::class)
            ->call('toggleFlag', 'surge_pricing');

        $this->assertDatabaseHas('feature_flag_overrides', [
            'flag_key' => 'surge_pricing',
            'is_enabled' => false,
            'reason' => 'Quick toggle via admin UI',
            'updated_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_toggle_flag_twice_flips_existing_override(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(FeatureFlagsManager::class);

        $component->call('toggleFlag', 'chat_v2');
        $this->assertFalse((bool) FeatureFlagOverride::where('flag_key', 'chat_v2')->first()->is_enabled);

        $component->call('toggleFlag', 'chat_v2');
        $this->assertTrue((bool) FeatureFlagOverride::where('flag_key', 'chat_v2')->first()->is_enabled);
    }

    public function test_toggle_flag_for_unknown_key_defaults_off_then_enables(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FeatureFlagsManager::class)
            ->call('toggleFlag', 'totally_unknown_flag');

        $this->assertDatabaseHas('feature_flag_overrides', [
            'flag_key' => 'totally_unknown_flag',
            'is_enabled' => true,
        ]);
    }

    public function test_edit_flag_loads_resolved_value_from_config(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FeatureFlagsManager::class)
            ->call('editFlag', 'premium_tiers')
            ->assertSet('editingKey', 'premium_tiers')
            ->assertSet('editingValue', true)
            ->assertSet('editReason', '');
    }

    public function test_edit_flag_loads_resolved_value_from_override(): void
    {
        FeatureFlagOverride::create([
            'flag_key' => 'fleet_management',
            'is_enabled' => false,
            'reason' => 'maintenance',
            'updated_by_user_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(FeatureFlagsManager::class)
            ->call('editFlag', 'fleet_management')
            ->assertSet('editingKey', 'fleet_management')
            ->assertSet('editingValue', false);
    }

    public function test_save_flag_creates_override_with_reason(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FeatureFlagsManager::class)
            ->call('editFlag', 'kyb_b2b')
            ->set('editingValue', false)
            ->set('editReason', 'Disabling temporarily')
            ->call('saveFlag')
            ->assertHasNoErrors()
            ->assertSet('editingKey', null);

        $this->assertDatabaseHas('feature_flag_overrides', [
            'flag_key' => 'kyb_b2b',
            'is_enabled' => false,
            'reason' => 'Disabling temporarily',
            'updated_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_save_flag_with_empty_reason_stores_null(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FeatureFlagsManager::class)
            ->call('editFlag', 'matching_v2')
            ->set('editReason', '')
            ->call('saveFlag')
            ->assertHasNoErrors();

        $override = FeatureFlagOverride::where('flag_key', 'matching_v2')->first();
        $this->assertNotNull($override);
        $this->assertNull($override->reason);
    }

    public function test_save_flag_validates_editing_key(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FeatureFlagsManager::class)
            ->set('editingKey', '')
            ->call('saveFlag')
            ->assertHasErrors(['editingKey' => 'required']);
    }

    public function test_remove_override_restores_config_value(): void
    {
        FeatureFlagOverride::create([
            'flag_key' => 'provider_badges',
            'is_enabled' => false,
            'reason' => 'temp',
            'updated_by_user_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(FeatureFlagsManager::class)
            ->call('removeOverride', 'provider_badges');

        $this->assertDatabaseMissing('feature_flag_overrides', [
            'flag_key' => 'provider_badges',
        ]);
    }

    public function test_render_applies_search_filter(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FeatureFlagsManager::class)
            ->set('search', 'surge')
            ->assertOk()
            ->assertSee('surge_pricing')
            ->assertDontSee('chat_v2');
    }

    public function test_render_shows_override_metadata_with_updated_by(): void
    {
        FeatureFlagOverride::create([
            'flag_key' => 'loyalty_redemption',
            'is_enabled' => false,
            'reason' => 'audit',
            'updated_by_user_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(FeatureFlagsManager::class)
            ->assertOk()
            ->assertSee('loyalty_redemption');
    }

    public function test_toggle_flag_forbidden_for_non_admin(): void
    {
        // EnforcesAdminAccess now blocks a non-admin at mount/boot (before any action).
        Livewire::actingAs($this->client)
            ->test(FeatureFlagsManager::class)
            ->assertForbidden();

        $this->assertDatabaseMissing('feature_flag_overrides', [
            'flag_key' => 'surge_pricing',
        ]);
    }
}
