<?php

namespace Tests\Feature;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use App\Notifications\AdminDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommunicationHealthCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_notifies_admin_when_google_connections_are_stale(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $employe = User::factory()->employe()->create();

        GoogleCalendarConnection::query()->create([
            'user_id' => $employe->id,
            'google_email' => 'agent@example.com',
            'google_user_id' => 'google-123',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->subHour(),
            'calendar_id' => 'primary',
            'sync_enabled' => true,
            'last_synced_at' => now()->subDays(2),
            'last_sync_status' => 'error',
            'last_sync_error' => 'Token expired',
        ]);

        Artisan::call('app:communication-health-check', ['--stale-hours' => 24]);

        Notification::assertSentTo($admin, AdminDigestNotification::class);
    }
}
