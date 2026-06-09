<?php

namespace Tests\Feature\Livewire\Provider;

use App\Livewire\Provider\ProviderPresenceToggle;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProviderPresenceToggleCoverageBatch14Test extends TestCase
{
    use RefreshDatabase;

    private function providerWithProfile(array $attributes = []): User
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::factory()->create(array_merge([
            'user_id' => $user->id,
            'status' => 'active',
        ], $attributes));

        return $user;
    }

    public function test_mount_reflects_online_profile_state(): void
    {
        $user = $this->providerWithProfile([
            'is_online' => true,
            'went_online_at' => now(),
        ]);
        $this->actingAs($user);

        $component = Livewire::test(ProviderPresenceToggle::class)
            ->assertOk()
            ->assertSet('isOnline', true);

        $this->assertNotNull($component->get('wentOnlineAt'));
    }

    public function test_mount_without_profile_stays_offline(): void
    {
        $this->actingAs(User::factory()->employe()->create());

        Livewire::test(ProviderPresenceToggle::class)
            ->assertOk()
            ->assertSet('isOnline', false)
            ->assertSet('wentOnlineAt', null);
    }

    public function test_go_online_flashes_success_and_dispatches(): void
    {
        $user = $this->providerWithProfile(['is_online' => false]);
        $this->actingAs($user);

        Livewire::test(ProviderPresenceToggle::class)
            ->call('goOnline', 48.8566, 2.3522, ['source' => 'web'])
            ->assertSet('isOnline', true)
            ->assertSet('messageType', 'success')
            ->assertDispatched('presence:online-confirmed');

        $this->assertDatabaseHas('provider_profiles', [
            'user_id' => $user->id,
            'is_online' => true,
        ]);
    }

    public function test_go_online_without_profile_flashes_error(): void
    {
        $this->actingAs(User::factory()->employe()->create());

        Livewire::test(ProviderPresenceToggle::class)
            ->call('goOnline', 48.0, 2.0)
            ->assertSet('isOnline', false)
            ->assertSet('messageType', 'error');
    }

    public function test_go_offline_flashes_success_and_dispatches(): void
    {
        $user = $this->providerWithProfile([
            'is_online' => true,
            'went_online_at' => now(),
        ]);
        $this->actingAs($user);

        Livewire::test(ProviderPresenceToggle::class)
            ->set('isOnline', true)
            ->call('goOffline')
            ->assertSet('isOnline', false)
            ->assertSet('wentOnlineAt', null)
            ->assertSet('messageType', 'success')
            ->assertDispatched('presence:offline-confirmed');

        $this->assertDatabaseHas('provider_profiles', [
            'user_id' => $user->id,
            'is_online' => false,
        ]);
    }

    public function test_heartbeat_noop_when_offline(): void
    {
        $user = $this->providerWithProfile(['is_online' => false]);
        $this->actingAs($user);

        Livewire::test(ProviderPresenceToggle::class)
            ->set('isOnline', false)
            ->call('heartbeat', 48.8, 2.3)
            ->assertSet('isOnline', false)
            ->assertNotDispatched('presence:offline-confirmed');
    }

    public function test_heartbeat_updates_position_when_online(): void
    {
        $user = $this->providerWithProfile([
            'is_online' => true,
            'went_online_at' => now(),
        ]);
        $this->actingAs($user);

        Livewire::test(ProviderPresenceToggle::class)
            ->set('isOnline', true)
            ->call('heartbeat', 49.1234, 3.4321, ['battery' => 80])
            ->assertSet('isOnline', true);

        $this->assertSame(49.1234, (float) $user->providerProfile->fresh()->current_lat);
    }

    public function test_heartbeat_detects_server_side_offline(): void
    {
        // Component believes it is online, but the persisted profile is offline,
        // so the service returns null and the component reconciles to offline.
        $user = $this->providerWithProfile(['is_online' => false]);
        $this->actingAs($user);

        Livewire::test(ProviderPresenceToggle::class)
            ->set('isOnline', true)
            ->call('heartbeat', 48.8, 2.3)
            ->assertSet('isOnline', false)
            ->assertDispatched('presence:offline-confirmed');
    }

    public function test_clear_message_resets_message_state(): void
    {
        $this->actingAs($this->providerWithProfile(['is_online' => false]));

        Livewire::test(ProviderPresenceToggle::class)
            ->call('goOnline', 48.0, 2.0)
            ->assertSet('messageType', 'success')
            ->call('clearMessage')
            ->assertSet('message', null)
            ->assertSet('messageType', null);
    }
}
