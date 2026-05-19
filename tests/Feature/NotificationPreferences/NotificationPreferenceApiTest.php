<?php

namespace Tests\Feature\NotificationPreferences;

use App\Models\NotificationPreference;
use App\Models\NotificationPreferenceAudit;
use App\Models\User;
use App\Services\NotificationPreferences\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPreferenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_requires_auth(): void
    {
        $this->getJson('/api/client/notifications/preferences')->assertStatus(401);
    }

    public function test_show_returns_default_matrix(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/client/notifications/preferences');

        $response->assertOk();
        $response->assertJsonStructure(['channels', 'categories', 'forced_on', 'preferences']);
        $this->assertTrue($response->json('preferences.email.transactional'));
        $this->assertFalse($response->json('preferences.email.marketing'));
    }

    public function test_update_single_preference(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/client/notifications/preferences', [
            'channel' => 'sms',
            'category' => 'marketing',
            'is_allowed' => true,
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $this->assertSame(1, NotificationPreference::count());
    }

    public function test_update_rejects_forced_on_pair_optout(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/client/notifications/preferences', [
            'channel' => 'email',
            'category' => 'verification',
            'is_allowed' => false,
        ]);

        $response->assertStatus(422);
    }

    public function test_update_validates_channel_and_category(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/client/notifications/preferences', [
            'channel' => 'whatsapp',
            'category' => 'arbitrary',
            'is_allowed' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_bulk_update(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/client/notifications/preferences/bulk', [
            'preferences' => [
                ['channel' => 'sms', 'category' => 'reminder', 'is_allowed' => false],
                ['channel' => 'push', 'category' => 'marketing', 'is_allowed' => true],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(2, $response->json('updated_count'));
    }

    public function test_audit_endpoint_returns_user_history(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        app(NotificationPreferenceService::class)->setPreference(
            $user, 'sms', 'reminder', false,
        );

        $response = $this->getJson('/api/client/notifications/preferences/audit');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_audit_endpoint_cross_user_isolation(): void
    {
        $alice = User::factory()->client()->create();
        $bob = User::factory()->client()->create();

        app(NotificationPreferenceService::class)->setPreference(
            $alice, 'sms', 'reminder', false,
        );

        Sanctum::actingAs($bob);
        $response = $this->getJson('/api/client/notifications/preferences/audit');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }
}
