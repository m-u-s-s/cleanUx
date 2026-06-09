<?php

namespace Tests\Feature\Admin\Sms;

use App\Livewire\Admin\Sms\SmsCenter;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms\Providers\SmsMockProvider;
use App\Services\Sms\SmsProviderInterface;
use Database\Factories\SmsMessageFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class SmsCenterCoverageBatch14Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(SmsProviderInterface::class, SmsMockProvider::class);
        Config::set('sms.enabled', true);
        Config::set('sms.rate_limits.per_phone_per_hour', 50);
        Config::set('sms.rate_limits.per_phone_per_day', 100);
        Config::set('sms.rate_limits.per_user_per_hour', 50);
    }

    public function test_renders_default_recent_tab_with_kpis(): void
    {
        $admin = User::factory()->admin()->create();

        SmsMessageFactory::new()->create([
            'status' => SmsMessage::STATUS_DELIVERED,
            'queued_at' => now(),
        ]);
        SmsMessageFactory::new()->create([
            'status' => SmsMessage::STATUS_FAILED,
            'queued_at' => now(),
        ]);
        SmsMessageFactory::new()->create([
            'status' => SmsMessage::STATUS_RATE_LIMITED,
            'queued_at' => now(),
        ]);
        SmsMessageFactory::new()->create([
            'status' => SmsMessage::STATUS_SENT,
            'queued_at' => now()->subDays(5),
        ]);

        Livewire::actingAs($admin)
            ->test(SmsCenter::class)
            ->assertOk()
            ->assertSet('tab', 'recent');
    }

    public function test_retry_resends_failed_message_and_logs(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->client()->create();

        $message = SmsMessageFactory::new()->create([
            'status' => SmsMessage::STATUS_FAILED,
            'to_phone' => '+32412345678',
            'body' => 'retry me',
            'category' => SmsMessage::CATEGORY_TRANSACTIONAL,
            'user_id' => $owner->id,
        ]);

        Livewire::actingAs($admin)
            ->test(SmsCenter::class)
            ->call('retry', $message->id)
            ->assertDispatched('toast');

        $this->assertTrue(SmsMessage::count() > 1);
    }

    public function test_retry_accepts_undelivered_and_rate_limited(): void
    {
        $admin = User::factory()->admin()->create();

        $undelivered = SmsMessageFactory::new()->create([
            'status' => SmsMessage::STATUS_UNDELIVERED,
            'to_phone' => '+32470111222',
            'category' => null,
        ]);
        $rateLimited = SmsMessageFactory::new()->create([
            'status' => SmsMessage::STATUS_RATE_LIMITED,
            'to_phone' => '+32470333444',
        ]);

        Livewire::actingAs($admin)
            ->test(SmsCenter::class)
            ->call('retry', $undelivered->id)
            ->assertDispatched('toast')
            ->call('retry', $rateLimited->id)
            ->assertDispatched('toast');
    }

    public function test_retry_rejects_non_retryable_status(): void
    {
        $admin = User::factory()->admin()->create();

        $delivered = SmsMessageFactory::new()->create([
            'status' => SmsMessage::STATUS_DELIVERED,
        ]);

        $before = SmsMessage::count();

        Livewire::actingAs($admin)
            ->test(SmsCenter::class)
            ->call('retry', $delivered->id)
            ->assertDispatched('toast');

        $this->assertSame($before, SmsMessage::count());
    }

    public function test_filters_and_search_render(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->client()->create(['name' => 'Searchable Person']);

        SmsMessageFactory::new()->create([
            'status' => SmsMessage::STATUS_FAILED,
            'provider' => 'mock',
            'category' => SmsMessage::CATEGORY_MARKETING,
            'to_phone' => '+32499888777',
            'external_id' => 'mock_sms_findme',
            'user_id' => $owner->id,
        ]);
        SmsMessageFactory::new()->create([
            'status' => SmsMessage::STATUS_DELIVERED,
            'provider' => 'mock',
        ]);

        Livewire::actingAs($admin)
            ->test(SmsCenter::class)
            ->set('filterStatus', SmsMessage::STATUS_FAILED)
            ->assertOk()
            ->set('filterProvider', 'mock')
            ->assertOk()
            ->set('filterCategory', SmsMessage::CATEGORY_MARKETING)
            ->assertOk()
            ->set('search', 'findme')
            ->assertOk()
            ->set('search', 'Searchable')
            ->assertOk();
    }
}
