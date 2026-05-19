<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('analytics.enabled', true);
        Config::set('analytics.allowed_events', [
            'booking.created',
            'booking.confirmed',
            'page.viewed',
            'search.performed',
            'unknown.never',
        ]);
    }

    public function test_track_creates_event_and_session(): void
    {
        $user = User::factory()->client()->create();

        $event = app(AnalyticsService::class)->track('booking.created', [
            'booking_id' => 42,
            'amount_cents' => 5000,
        ], [
            'user' => $user,
            'revenue_cents' => 5000,
            'currency' => 'EUR',
            'idempotency_key' => 'booking.created:42',
        ]);

        $this->assertNotNull($event);
        $this->assertSame('booking.created', $event->event_name);
        $this->assertSame(AnalyticsEvent::CATEGORY_LIFECYCLE, $event->event_category);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame(5000, (int) $event->revenue_cents);
        $this->assertSame('EUR', $event->currency);

        // Auto-created session
        $this->assertSame(1, AnalyticsSession::count());
        $session = AnalyticsSession::first();
        $this->assertSame($event->session_id, $session->session_id);
        $this->assertSame(1, (int) $session->event_count);
    }

    public function test_track_is_idempotent_with_same_key(): void
    {
        $svc = app(AnalyticsService::class);

        $a = $svc->track('booking.created', ['x' => 1], ['idempotency_key' => 'k1']);
        $b = $svc->track('booking.created', ['x' => 2], ['idempotency_key' => 'k1']);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, AnalyticsEvent::count());
    }

    public function test_track_drops_events_not_in_whitelist(): void
    {
        $event = app(AnalyticsService::class)->track('arbitrary.injected', []);

        $this->assertNull($event);
        $this->assertSame(0, AnalyticsEvent::count());
    }

    public function test_track_skipped_when_analytics_disabled(): void
    {
        Config::set('analytics.enabled', false);

        $event = app(AnalyticsService::class)->track('booking.created', []);

        $this->assertNull($event);
    }

    public function test_sanitize_drops_password_token_keys(): void
    {
        $svc = app(AnalyticsService::class);

        $event = $svc->track('booking.created', [
            'booking_id' => 1,
            'password' => 'secret',
            'token' => 'abc',
            'api_key' => 'xyz',
            'safe_field' => 'kept',
        ]);

        $props = $event->properties;
        $this->assertArrayNotHasKey('password', $props);
        $this->assertArrayNotHasKey('token', $props);
        $this->assertArrayNotHasKey('api_key', $props);
        $this->assertSame('kept', $props['safe_field']);
    }

    public function test_sanitize_hashes_email_and_phone(): void
    {
        $svc = app(AnalyticsService::class);

        $event = $svc->track('booking.created', [
            'email' => 'user@example.com',
            'phone' => '+32412345678',
        ]);

        $this->assertStringStartsWith('sha256:', $event->properties['email']);
        $this->assertStringStartsWith('sha256:', $event->properties['phone']);
        $this->assertStringNotContainsString('user@example.com', json_encode($event->properties));
    }

    public function test_sanitize_truncates_long_strings(): void
    {
        Config::set('analytics.sanitize.max_string_length', 50);
        $svc = app(AnalyticsService::class);

        $long = str_repeat('a', 200);
        $event = $svc->track('booking.created', ['blob' => $long]);

        $this->assertSame(50, strlen($event->properties['blob']));
    }

    public function test_identify_links_anonymous_events_to_user(): void
    {
        $svc = app(AnalyticsService::class);

        $svc->track('page.viewed', [], ['anonymous_id' => 'anon_xyz']);
        $svc->track('search.performed', [], ['anonymous_id' => 'anon_xyz']);

        $user = User::factory()->client()->create();
        $count = $svc->identify('anon_xyz', $user);

        $this->assertGreaterThanOrEqual(2, $count);

        $this->assertSame(0, AnalyticsEvent::query()
            ->where('anonymous_id', 'anon_xyz')
            ->whereNull('user_id')
            ->count());
    }

    public function test_session_reused_when_within_inactivity_window(): void
    {
        Config::set('analytics.session.inactivity_minutes', 30);
        $svc = app(AnalyticsService::class);

        $a = $svc->track('page.viewed', []);
        $b = $svc->track('search.performed', [], ['session_id' => $a->session_id]);

        $this->assertSame($a->session_id, $b->session_id);
        $this->assertSame(1, AnalyticsSession::count());
    }

    public function test_session_rotated_when_expired(): void
    {
        Config::set('analytics.session.inactivity_minutes', 1);
        $svc = app(AnalyticsService::class);

        $a = $svc->track('page.viewed', []);

        AnalyticsSession::where('session_id', $a->session_id)
            ->update(['last_seen_at' => now()->subHours(2)]);

        $b = $svc->track('search.performed', [], ['session_id' => $a->session_id]);

        $this->assertNotSame($a->session_id, $b->session_id);
    }
}
