<?php

namespace Tests\Feature\Marketing;

use App\Models\MarketingOptOut;
use App\Models\User;
use App\Services\Marketing\OptOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptOutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_opt_out_email_creates_record(): void
    {
        $user = User::factory()->client()->create();

        $row = app(OptOutService::class)->optOut($user, 'email', reason: 'too many');

        $this->assertSame(1, MarketingOptOut::count());
        $this->assertSame('email', $row->channel);
        $this->assertTrue(app(OptOutService::class)->isOptedOut($user, 'email'));
        $this->assertFalse(app(OptOutService::class)->isOptedOut($user, 'sms'));
    }

    public function test_opt_out_all_channel_covers_all_sub_channels(): void
    {
        $user = User::factory()->client()->create();

        app(OptOutService::class)->optOut($user, 'all');

        $this->assertTrue(app(OptOutService::class)->isOptedOut($user, 'email'));
        $this->assertTrue(app(OptOutService::class)->isOptedOut($user, 'sms'));
        $this->assertTrue(app(OptOutService::class)->isOptedOut($user, 'push'));
    }

    public function test_opt_out_idempotent_same_channel(): void
    {
        $user = User::factory()->client()->create();

        app(OptOutService::class)->optOut($user, 'email');
        app(OptOutService::class)->optOut($user, 'email');

        $this->assertSame(1, MarketingOptOut::count());
    }

    public function test_opt_in_removes_record(): void
    {
        $user = User::factory()->client()->create();
        app(OptOutService::class)->optOut($user, 'email');

        $deleted = app(OptOutService::class)->optIn($user, 'email');

        $this->assertTrue($deleted);
        $this->assertSame(0, MarketingOptOut::count());
        $this->assertFalse(app(OptOutService::class)->isOptedOut($user, 'email'));
    }

    public function test_preferences_returns_full_state(): void
    {
        $user = User::factory()->client()->create();
        app(OptOutService::class)->optOut($user, 'email');
        app(OptOutService::class)->optOut($user, 'sms');

        $prefs = app(OptOutService::class)->preferences($user);

        $this->assertTrue($prefs['email']);
        $this->assertTrue($prefs['sms']);
        $this->assertFalse($prefs['push']);
        $this->assertFalse($prefs['all']);
    }
}
