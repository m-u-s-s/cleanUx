<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('analytics.enabled', true);
        Config::set('analytics.allowed_events', [
            'page.viewed', 'search.performed', 'booking.created',
        ]);
        Config::set('analytics.rate_limits.per_ip_per_minute', 100);
        Config::set('analytics.rate_limits.per_user_per_minute', 100);
    }

    public function test_track_endpoint_accepts_anonymous_event(): void
    {
        $response = $this->postJson('/api/analytics/track', [
            'event' => 'page.viewed',
            'properties' => ['path' => '/home'],
            'anonymous_id' => 'anon_aaa',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['ok' => true, 'tracked' => true]);

        $this->assertSame(1, AnalyticsEvent::query()->where('event_name', 'page.viewed')->count());
    }

    public function test_track_endpoint_validates_event_required(): void
    {
        $this->postJson('/api/analytics/track', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event']);
    }

    public function test_track_endpoint_silently_drops_unknown_event(): void
    {
        $response = $this->postJson('/api/analytics/track', [
            'event' => 'arbitrary.bad',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'tracked' => false]);
        $this->assertSame(0, AnalyticsEvent::count());
    }

    public function test_track_endpoint_links_user_when_authenticated(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/analytics/track', [
            'event' => 'search.performed',
            'properties' => ['query' => 'plomberie'],
        ]);

        $response->assertStatus(201);
        $event = AnalyticsEvent::first();
        $this->assertSame($user->id, $event->user_id);
    }

    public function test_page_endpoint_forces_page_viewed(): void
    {
        $response = $this->postJson('/api/analytics/page', [
            'url' => 'https://cleanux.test/home',
            'properties' => ['section' => 'hero'],
        ]);

        $response->assertStatus(201);
        $event = AnalyticsEvent::first();
        $this->assertSame('page.viewed', $event->event_name);
    }

    public function test_identify_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/analytics/identify', [
            'anonymous_id' => 'anon_xyz',
        ])->assertStatus(401);
    }

    public function test_identify_endpoint_links_anonymous_events_to_user(): void
    {
        // Create anon events first
        $this->postJson('/api/analytics/track', [
            'event' => 'page.viewed',
            'anonymous_id' => 'anon_link',
        ])->assertStatus(201);

        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/analytics/identify', [
            'anonymous_id' => 'anon_link',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['ok', 'linked_count']);
        $this->assertGreaterThanOrEqual(1, $response->json('linked_count'));
    }
}
