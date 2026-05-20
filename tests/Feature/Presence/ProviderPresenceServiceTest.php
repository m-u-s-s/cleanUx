<?php

namespace Tests\Feature\Presence;

use App\Models\ProviderPresence;
use App\Models\User;
use App\Services\Presence\ProviderPresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProviderPresenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_go_online_creates_presence_with_status_online(): void
    {
        $provider = User::factory()->employe()->create();

        $presence = app(ProviderPresenceService::class)->goOnline($provider, 48.8566, 2.3522, 10, 'iPhone 15');

        $this->assertInstanceOf(ProviderPresence::class, $presence);
        $this->assertSame(ProviderPresence::STATUS_ONLINE, $presence->status);
        $this->assertSame(48.8566, (float) $presence->current_lat);
        $this->assertSame(2.3522, (float) $presence->current_lng);
        $this->assertSame(10, (int) $presence->available_radius_km);
        $this->assertSame('iPhone 15', $presence->device_info);
        $this->assertNotNull($presence->heartbeat_at);
        $this->assertNotNull($presence->last_online_at);
    }

    public function test_go_online_idempotent_on_already_online(): void
    {
        $provider = User::factory()->employe()->create();
        $service = app(ProviderPresenceService::class);

        $p1 = $service->goOnline($provider);
        $firstChange = $p1->last_status_change_at;
        Carbon::setTestNow(Carbon::now()->addMinute());

        $p2 = $service->goOnline($provider);
        $this->assertSame($p1->id, $p2->id);
        // last_status_change_at ne doit pas changer car déjà online
        $this->assertEquals($firstChange->toIso8601String(), $p2->last_status_change_at?->toIso8601String());

        Carbon::setTestNow();
    }

    public function test_heartbeat_updates_timestamp_and_minutes_counter(): void
    {
        $provider = User::factory()->employe()->create();
        $service = app(ProviderPresenceService::class);

        $service->goOnline($provider);

        Carbon::setTestNow(Carbon::now()->addMinutes(3));
        $presence = $service->heartbeat($provider, 48.86, 2.36);

        $this->assertSame(48.86, (float) $presence->current_lat);
        $this->assertGreaterThanOrEqual(3, (int) $presence->online_minutes_today);

        Carbon::setTestNow();
    }

    public function test_heartbeat_rejects_offline_provider(): void
    {
        $provider = User::factory()->employe()->create();

        $this->expectException(ValidationException::class);
        app(ProviderPresenceService::class)->heartbeat($provider);
    }

    public function test_go_busy_transitions_correctly(): void
    {
        $provider = User::factory()->employe()->create();
        $service = app(ProviderPresenceService::class);

        $service->goOnline($provider);
        $busy = $service->goBusy($provider);

        $this->assertSame(ProviderPresence::STATUS_BUSY, $busy->status);
        $this->assertNotNull($busy->heartbeat_at);
    }

    public function test_go_offline_clears_status(): void
    {
        $provider = User::factory()->employe()->create();
        $service = app(ProviderPresenceService::class);

        $service->goOnline($provider);
        $offline = $service->goOffline($provider);

        $this->assertSame(ProviderPresence::STATUS_OFFLINE, $offline->status);
    }

    public function test_scan_stale_auto_offline_after_threshold(): void
    {
        $provider1 = User::factory()->employe()->create();
        $provider2 = User::factory()->employe()->create();
        $service = app(ProviderPresenceService::class);

        // Provider 1 online avec heartbeat récent
        $service->goOnline($provider1);

        // Provider 2 online mais heartbeat ancien (simulé)
        $service->goOnline($provider2);
        ProviderPresence::query()
            ->where('provider_user_id', $provider2->id)
            ->update(['heartbeat_at' => now()->subMinutes(10)]);

        $countTransitioned = $service->scanStale(5);

        $this->assertSame(1, $countTransitioned);
        $p1 = ProviderPresence::query()->where('provider_user_id', $provider1->id)->first();
        $p2 = ProviderPresence::query()->where('provider_user_id', $provider2->id)->first();

        $this->assertSame(ProviderPresence::STATUS_ONLINE, $p1->status);
        $this->assertSame(ProviderPresence::STATUS_OFFLINE, $p2->status);
        $this->assertSame('stale_heartbeat', $p2->metadata['auto_offline_reason'] ?? null);
    }

    public function test_available_provider_ids_returns_only_online_with_recent_heartbeat(): void
    {
        $online = User::factory()->employe()->create();
        $busy = User::factory()->employe()->create();
        $stale = User::factory()->employe()->create();
        $offline = User::factory()->employe()->create();
        $service = app(ProviderPresenceService::class);

        $service->goOnline($online);
        $service->goOnline($busy);
        $service->goBusy($busy);
        $service->goOnline($stale);
        ProviderPresence::query()->where('provider_user_id', $stale->id)
            ->update(['heartbeat_at' => now()->subMinutes(10)]);
        // offline n'est pas créé

        $availableIds = $service->availableProviderIds(5);

        $this->assertContains($online->id, $availableIds);
        $this->assertNotContains($busy->id, $availableIds);   // busy n'est pas "available"
        $this->assertNotContains($stale->id, $availableIds);  // stale heartbeat
        $this->assertNotContains($offline->id, $availableIds);
    }

    public function test_transition_no_op_if_same_status(): void
    {
        $provider = User::factory()->employe()->create();
        $service = app(ProviderPresenceService::class);

        $service->goOnline($provider);
        $service->goOnline($provider);  // idempotent

        $count = ProviderPresence::query()->where('provider_user_id', $provider->id)->count();
        $this->assertSame(1, $count);
    }
}
