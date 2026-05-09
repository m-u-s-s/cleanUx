<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_records_authenticated_user_action_and_target(): void
    {
        $user = User::factory()->admin()->create();
        $rdv = Booking::factory()->create();

        $this->actingAs($user);

        ActivityLogger::log('test_action', $rdv, ['source' => 'phpunit']);

        $log = ActivityLog::first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('test_action', $log->action);
        $this->assertSame(Booking::class, $log->target_type);
        $this->assertSame($rdv->id, $log->target_id);
        $this->assertSame('phpunit', $log->meta['source']);
    }

    public function test_system_records_action_without_authenticated_user(): void
    {
        ActivityLogger::system('system_action', null, ['channel' => 'scheduler']);

        $log = ActivityLog::first();

        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
        $this->assertSame('system_action', $log->action);
        $this->assertSame('scheduler', $log->meta['channel']);
    }
}
