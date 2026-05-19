<?php

namespace Tests\Feature\Gdpr;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Gdpr\RetentionPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RetentionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_activity_logs_are_purged(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            $this->markTestSkipped('activity_logs table missing');
        }

        $user = User::factory()->create();

        // Log très ancien (au-delà de la retention)
        $oldRetentionDays = (int) config('gdpr.retention.activity_logs_days', 730);
        DB::table('activity_logs')->insert([
            'user_id' => $user->id,
            'action' => 'test.old',
            'created_at' => now()->subDays($oldRetentionDays + 10),
            'updated_at' => now()->subDays($oldRetentionDays + 10),
        ]);

        DB::table('activity_logs')->insert([
            'user_id' => $user->id,
            'action' => 'test.recent',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $stats = app(RetentionPolicyService::class)->enforceAll();

        $this->assertGreaterThanOrEqual(1, $stats['activity_logs']);
        $this->assertSame(0, DB::table('activity_logs')->where('action', 'test.old')->count());
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'test.recent')->count());
    }

    public function test_unread_notifications_are_not_purged(): void
    {
        if (! Schema::hasTable('notifications')) {
            $this->markTestSkipped('notifications table missing');
        }

        $user = User::factory()->create();
        $oldDays = (int) config('gdpr.retention.notifications_days', 365);

        // Non lue très ancienne — préservée
        DB::table('notifications')->insert([
            'id' => 'aaaa-aaaa-aaaa-aaaa',
            'type' => 'TestUnread',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['msg' => 'unread']),
            'read_at' => null,
            'created_at' => now()->subDays($oldDays + 50),
            'updated_at' => now()->subDays($oldDays + 50),
        ]);

        // Lue très ancienne — purgée
        DB::table('notifications')->insert([
            'id' => 'bbbb-bbbb-bbbb-bbbb',
            'type' => 'TestRead',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['msg' => 'read']),
            'read_at' => now()->subDays($oldDays + 30),
            'created_at' => now()->subDays($oldDays + 50),
            'updated_at' => now()->subDays($oldDays + 30),
        ]);

        app(RetentionPolicyService::class)->enforceAll();

        $this->assertSame(1, DB::table('notifications')->where('type', 'TestUnread')->count());
        $this->assertSame(0, DB::table('notifications')->where('type', 'TestRead')->count());
    }
}
