<?php

namespace Tests\Feature\Push;

use App\Livewire\Admin\Push\PushCenter;
use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\Push\Providers\PushMockProvider;
use App\Services\Push\PushProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class PushCenterCoverageBatch11Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PushProviderInterface::class, PushMockProvider::class);
        Config::set('push.enabled', true);
        Config::set('push.rate_limits.per_token_per_minute', 50);
        Config::set('push.rate_limits.per_user_per_minute', 100);
        Config::set('push.fcm.project_id', null);
        Config::set('push.fcm.credentials_path', null);
        Config::set('push.apns.key_path', null);
        Config::set('push.apns.key_id', null);
    }

    protected function makeToken(?User $user = null, array $overrides = []): DeviceToken
    {
        $user ??= User::factory()->client()->create();
        $raw = $overrides['token'] ?? 'tok_'.uniqid();

        return DeviceToken::create(array_merge([
            'user_id' => $user->id,
            'platform' => DeviceToken::PLATFORM_ANDROID,
            'provider' => DeviceToken::PROVIDER_MOCK,
            'token' => $raw,
            'token_hash' => DeviceToken::hashToken($raw),
            'preferences' => [
                'transactional' => true,
                'verification' => true,
                'reminder' => true,
                'marketing' => true,
            ],
            'last_used_at' => now(),
        ], $overrides));
    }

    protected function makeNotif(array $overrides = []): PushNotification
    {
        return PushNotification::create(array_merge([
            'provider' => 'mock',
            'title' => 'Hello',
            'body' => 'World',
            'category' => PushNotification::CATEGORY_TRANSACTIONAL,
            'status' => PushNotification::STATUS_SENT,
            'queued_at' => now(),
        ], $overrides));
    }

    public function test_renders_with_kpis_and_items(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->makeToken();

        $this->makeNotif(['user_id' => $token->user_id, 'device_token_id' => $token->id, 'status' => PushNotification::STATUS_DELIVERED]);
        $this->makeNotif(['status' => PushNotification::STATUS_FAILED]);
        $this->makeNotif(['status' => PushNotification::STATUS_OPTED_OUT]);
        $this->makeNotif(['status' => PushNotification::STATUS_INVALID_TOKEN]);

        Livewire::actingAs($admin)
            ->test(PushCenter::class)
            ->assertOk()
            ->assertViewHas('kpis', function ($kpis) {
                return $kpis['total_24h'] === 4
                    && $kpis['delivered_24h'] === 1
                    && $kpis['failed_24h'] === 2
                    && $kpis['opted_out_24h'] === 1
                    && $kpis['active_tokens'] === 1;
            });
    }

    public function test_filters_and_search_apply(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create(['name' => 'Zoldyck', 'email' => 'zoldyck@example.com']);

        $this->makeNotif(['user_id' => $client->id, 'status' => PushNotification::STATUS_FAILED, 'provider' => 'fcm', 'category' => PushNotification::CATEGORY_MARKETING, 'title' => 'NeedleSearch', 'external_id' => 'ext-123']);
        $this->makeNotif(['status' => PushNotification::STATUS_SENT, 'provider' => 'apns', 'category' => PushNotification::CATEGORY_TRANSACTIONAL, 'title' => 'Other']);

        Livewire::actingAs($admin)
            ->test(PushCenter::class)
            ->set('filterStatus', PushNotification::STATUS_FAILED)
            ->assertOk()
            ->set('filterProvider', 'fcm')
            ->assertOk()
            ->set('filterCategory', PushNotification::CATEGORY_MARKETING)
            ->assertOk()
            ->set('search', 'NeedleSearch')
            ->assertOk()
            ->set('search', 'Zoldyck')
            ->assertOk();
    }

    public function test_retry_success_dispatches_success_toast(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->makeToken();
        $notif = $this->makeNotif([
            'user_id' => $token->user_id,
            'device_token_id' => $token->id,
            'status' => PushNotification::STATUS_FAILED,
            'locale' => 'fr',
        ]);

        /*
         * L'HORLOGE EST FIGÉE, sinon ce test tombe une fois de temps en temps.
         *
         * La clé d'idempotence est bâtie sur `now()->timestamp` — côté code au moment du renvoi,
         * côté test au moment de l'assertion. Quand la seconde change entre les deux, le test
         * attend `retry:1:…585` là où la base porte `retry:1:…584`, et l'échec n'apprend rien sur
         * le produit : il dit seulement que la suite a franchi une frontière de seconde.
         */
        Carbon::setTestNow(Carbon::now());

        try {
            Livewire::actingAs($admin)
                ->test(PushCenter::class)
                ->call('retry', $notif->id)
                ->assertDispatched('toast');

            $this->assertDatabaseHas('push_notifications', [
                'device_token_id' => $token->id,
                'idempotency_key' => 'retry:'.$notif->id.':'.now()->timestamp,
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_retry_rejects_non_failed_status(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->makeToken();
        $notif = $this->makeNotif([
            'user_id' => $token->user_id,
            'device_token_id' => $token->id,
            'status' => PushNotification::STATUS_DELIVERED,
        ]);

        Livewire::actingAs($admin)
            ->test(PushCenter::class)
            ->call('retry', $notif->id)
            ->assertDispatched('toast');

        // No new notification was created (only the original remains).
        $this->assertSame(1, PushNotification::count());
    }

    public function test_retry_rejects_when_token_missing(): void
    {
        $admin = User::factory()->admin()->create();
        $notif = $this->makeNotif([
            'status' => PushNotification::STATUS_RATE_LIMITED,
            'device_token_id' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(PushCenter::class)
            ->call('retry', $notif->id)
            ->assertDispatched('toast');

        $this->assertSame(1, PushNotification::count());
    }

    public function test_retry_rejects_when_token_inactive(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->makeToken();
        $token->invalidate('unsubscribed');

        $notif = $this->makeNotif([
            'user_id' => $token->user_id,
            'device_token_id' => $token->id,
            'status' => PushNotification::STATUS_FAILED,
        ]);

        Livewire::actingAs($admin)
            ->test(PushCenter::class)
            ->call('retry', $notif->id)
            ->assertDispatched('toast');

        $this->assertSame(1, PushNotification::count());
    }
}
