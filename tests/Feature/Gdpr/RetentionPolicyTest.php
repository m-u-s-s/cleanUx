<?php

namespace Tests\Feature\Gdpr;

use App\Models\GdprDataRequest;
use App\Models\User;
use App\Services\Gdpr\RetentionPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

    /** M14 — analytics_events past retention are purged; recent ones kept. */
    public function test_old_analytics_events_are_purged(): void
    {
        if (! Schema::hasTable('analytics_events')) {
            $this->markTestSkipped('analytics_events table missing');
        }

        $days = (int) config('gdpr.retention.analytics_events_days', 425);
        DB::table('analytics_events')->insert([
            'event_name' => 'old.event',
            'occurred_at' => now()->subDays($days + 10),
            'created_at' => now()->subDays($days + 10),
            'updated_at' => now()->subDays($days + 10),
        ]);
        DB::table('analytics_events')->insert([
            'event_name' => 'recent.event',
            'occurred_at' => now()->subDays(5),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        app(RetentionPolicyService::class)->enforceAll();

        $this->assertSame(0, DB::table('analytics_events')->where('event_name', 'old.event')->count());
        $this->assertSame(1, DB::table('analytics_events')->where('event_name', 'recent.event')->count());
    }

    /** M13 — expired GDPR export files are deleted from disk and the path is nulled. */
    public function test_expired_gdpr_export_files_are_deleted(): void
    {
        $disk = (string) config('gdpr.export_disk', 'local');
        Storage::fake($disk);
        Storage::disk($disk)->put('gdpr-exports/dump.json', '{"pii":"secret"}');

        $user = User::factory()->create();
        $request = GdprDataRequest::create([
            'user_id' => $user->id,
            'type' => GdprDataRequest::TYPE_EXPORT,
            'status' => GdprDataRequest::STATUS_FULFILLED,
            'reference' => 'GDPR-EXP-1',
            'requested_at' => now()->subDays(40),
            'export_file_path' => 'gdpr-exports/dump.json',
            'expires_at' => now()->subDay(),
        ]);

        app(RetentionPolicyService::class)->enforceAll();

        Storage::disk($disk)->assertMissing('gdpr-exports/dump.json');
        $this->assertNull($request->fresh()->export_file_path);
    }

    /** M13 — non-expired export files are preserved. */
    public function test_non_expired_gdpr_export_files_are_kept(): void
    {
        $disk = (string) config('gdpr.export_disk', 'local');
        Storage::fake($disk);
        Storage::disk($disk)->put('gdpr-exports/keep.json', '{"pii":"secret"}');

        $user = User::factory()->create();
        $request = GdprDataRequest::create([
            'user_id' => $user->id,
            'type' => GdprDataRequest::TYPE_EXPORT,
            'status' => GdprDataRequest::STATUS_FULFILLED,
            'reference' => 'GDPR-EXP-2',
            'requested_at' => now(),
            'export_file_path' => 'gdpr-exports/keep.json',
            'expires_at' => now()->addDays(20),
        ]);

        app(RetentionPolicyService::class)->enforceAll();

        Storage::disk($disk)->assertExists('gdpr-exports/keep.json');
        $this->assertSame('gdpr-exports/keep.json', $request->fresh()->export_file_path);
    }
}
