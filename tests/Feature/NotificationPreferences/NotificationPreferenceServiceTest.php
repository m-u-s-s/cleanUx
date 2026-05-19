<?php

namespace Tests\Feature\NotificationPreferences;

use App\Models\DeviceToken;
use App\Models\MarketingOptOut;
use App\Models\NotificationPreference;
use App\Models\NotificationPreferenceAudit;
use App\Models\User;
use App\Services\NotificationPreferences\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NotificationPreferenceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('notification_preferences.enabled', true);
        Config::set('notification_preferences.channels', ['email', 'sms', 'push', 'inapp', 'webhook']);
        Config::set('notification_preferences.categories', [
            'transactional', 'verification', 'reminder', 'marketing', 'support', 'security', 'product',
        ]);
        Config::set('notification_preferences.forced_on', [
            ['channel' => 'email', 'category' => 'verification'],
            ['channel' => 'email', 'category' => 'transactional'],
            ['channel' => 'email', 'category' => 'security'],
        ]);
        Config::set('notification_preferences.default_matrix', [
            'email' => [
                'transactional' => true,
                'marketing' => false,
                'verification' => true,
                'security' => true,
                'reminder' => true,
                'support' => true,
                'product' => false,
            ],
            'sms' => [
                'transactional' => true,
                'marketing' => false,
                'verification' => true,
                'security' => true,
                'reminder' => true,
                'support' => false,
                'product' => false,
            ],
            'push' => [
                'transactional' => true,
                'marketing' => false,
                'verification' => true,
                'security' => true,
                'reminder' => true,
                'support' => true,
                'product' => false,
            ],
            'inapp' => [
                'transactional' => true,
                'marketing' => true,
                'verification' => true,
                'security' => true,
                'reminder' => true,
                'support' => true,
                'product' => true,
            ],
            'webhook' => [
                'transactional' => true,
                'marketing' => false,
                'verification' => true,
                'security' => true,
                'reminder' => false,
                'support' => true,
                'product' => false,
            ],
        ]);
        Config::set('notification_preferences.sync_to_modules', [
            'push' => true,
            'marketing' => true,
        ]);
    }

    public function test_is_allowed_returns_default_when_no_row(): void
    {
        $user = User::factory()->client()->create();

        $svc = app(NotificationPreferenceService::class);

        $this->assertTrue($svc->isAllowed($user, 'email', 'transactional'));
        $this->assertFalse($svc->isAllowed($user, 'email', 'marketing'));
    }

    public function test_is_allowed_returns_user_value_when_row_exists(): void
    {
        $user = User::factory()->client()->create();

        $svc = app(NotificationPreferenceService::class);
        $svc->setPreference($user, 'sms', 'reminder', false);

        $this->assertFalse($svc->isAllowed($user, 'sms', 'reminder'));
    }

    public function test_set_preference_creates_row_and_audit(): void
    {
        $user = User::factory()->client()->create();

        app(NotificationPreferenceService::class)->setPreference(
            $user, 'email', 'marketing', true,
        );

        $this->assertSame(1, NotificationPreference::count());
        $row = NotificationPreference::first();
        $this->assertSame(1, (int) $row->version);
        $this->assertTrue($row->is_allowed);

        $this->assertSame(1, NotificationPreferenceAudit::count());
        $audit = NotificationPreferenceAudit::first();
        $this->assertNull($audit->old_value);
        $this->assertTrue($audit->new_value);
    }

    public function test_set_preference_increments_version_on_change(): void
    {
        $user = User::factory()->client()->create();
        $svc = app(NotificationPreferenceService::class);

        $svc->setPreference($user, 'sms', 'reminder', false);
        $svc->setPreference($user, 'sms', 'reminder', true);
        $svc->setPreference($user, 'sms', 'reminder', false);

        $row = NotificationPreference::first();
        $this->assertSame(3, (int) $row->version);
        $this->assertSame(3, NotificationPreferenceAudit::count());
    }

    public function test_set_preference_blocked_for_forced_on_pair(): void
    {
        $user = User::factory()->client()->create();

        $this->expectException(ValidationException::class);

        // email + verification is forced_on → cannot opt-out
        app(NotificationPreferenceService::class)->setPreference(
            $user, 'email', 'verification', false,
        );
    }

    public function test_set_preference_allowed_for_forced_on_pair_when_turning_on(): void
    {
        $user = User::factory()->client()->create();

        $pref = app(NotificationPreferenceService::class)->setPreference(
            $user, 'email', 'verification', true,
        );

        $this->assertTrue($pref->is_allowed);
    }

    public function test_get_preferences_returns_full_matrix(): void
    {
        $user = User::factory()->client()->create();

        $matrix = app(NotificationPreferenceService::class)->getPreferences($user);

        $this->assertArrayHasKey('email', $matrix);
        $this->assertArrayHasKey('marketing', $matrix['email']);
        $this->assertTrue($matrix['email']['transactional']);
        $this->assertFalse($matrix['email']['marketing']);
    }

    public function test_apply_defaults_creates_full_matrix(): void
    {
        $user = User::factory()->client()->create();

        $count = app(NotificationPreferenceService::class)->applyDefaultsFor($user);

        // 5 channels × 7 categories = 35
        $this->assertSame(35, $count);
        $this->assertSame(35, NotificationPreference::count());
    }

    public function test_apply_defaults_is_idempotent(): void
    {
        $user = User::factory()->client()->create();

        $svc = app(NotificationPreferenceService::class);
        $svc->applyDefaultsFor($user);
        $count = $svc->applyDefaultsFor($user);

        $this->assertSame(0, $count);  // nothing new inserted
        $this->assertSame(35, NotificationPreference::count());
    }

    public function test_set_many_processes_bulk_skipping_forced_on(): void
    {
        $user = User::factory()->client()->create();

        $results = app(NotificationPreferenceService::class)->setMany($user, [
            ['channel' => 'sms', 'category' => 'marketing', 'is_allowed' => true],
            ['channel' => 'email', 'category' => 'verification', 'is_allowed' => false],  // skipped (forced-on)
            ['channel' => 'push', 'category' => 'reminder', 'is_allowed' => false],
        ]);

        $this->assertCount(2, $results);  // forced-on skipped
        $this->assertSame(2, NotificationPreference::count());
    }

    public function test_sync_to_external_modules_propagates_to_push_device_tokens(): void
    {
        $user = User::factory()->client()->create();

        $token = DeviceToken::create([
            'user_id' => $user->id,
            'platform' => 'ios',
            'provider' => 'mock',
            'token' => 'tok1',
            'token_hash' => DeviceToken::hashToken('tok1'),
            'preferences' => ['marketing' => true],
            'last_used_at' => now(),
        ]);

        app(NotificationPreferenceService::class)->setPreference(
            $user, 'push', 'marketing', false,
        );

        $token->refresh();
        $this->assertFalse($token->preferences['marketing']);
    }

    public function test_sync_to_external_modules_propagates_marketing_optout(): void
    {
        $user = User::factory()->client()->create();

        app(NotificationPreferenceService::class)->setPreference(
            $user, 'email', 'marketing', false,
        );

        $this->assertSame(1, MarketingOptOut::query()
            ->where('user_id', $user->id)
            ->where('channel', 'email')
            ->count());
    }

    public function test_sync_to_external_modules_removes_marketing_optout_when_re_enabled(): void
    {
        $user = User::factory()->client()->create();
        MarketingOptOut::create([
            'user_id' => $user->id,
            'channel' => 'email',
            'opted_out_at' => now(),
        ]);

        app(NotificationPreferenceService::class)->setPreference(
            $user, 'email', 'marketing', true,
        );

        $this->assertSame(0, MarketingOptOut::count());
    }
}
