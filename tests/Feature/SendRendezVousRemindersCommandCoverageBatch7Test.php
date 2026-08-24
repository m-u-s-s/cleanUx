<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Notifications\AdminDigestNotification;
use App\Notifications\RappelRendezVousNotification;
use App\Notifications\UrgenceRendezVousNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendRendezVousRemindersCommandCoverageBatch7Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_runs_clean_with_no_data(): void
    {
        Notification::fake();

        Artisan::call('app:send-rendezvous-reminders');

        $this->assertStringContainsString('Rappels et relances envoyés.', Artisan::output());
        Notification::assertNothingSent();
    }

    public function test_24h_reminder_is_sent_and_marked(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));

        $client = User::factory()->client()->create();

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'confirme',
            'date' => '2026-06-10',
            'heure' => '10:05:00',
            'rappel_24h_envoye_at' => null,
        ]);

        Artisan::call('app:send-rendezvous-reminders');

        Notification::assertSentTo(
            $client,
            RappelRendezVousNotification::class,
            fn (RappelRendezVousNotification $n) => $n->timing === '24h'
        );

        $this->assertNotNull($booking->fresh()->rappel_24h_envoye_at);
    }

    public function test_2h_reminder_is_sent_and_marked(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));

        $client = User::factory()->client()->create();

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'confirme',
            'date' => '2026-06-09',
            'heure' => '12:05:00',
            'rappel_2h_envoye_at' => null,
        ]);

        Artisan::call('app:send-rendezvous-reminders');

        Notification::assertSentTo(
            $client,
            RappelRendezVousNotification::class,
            fn (RappelRendezVousNotification $n) => $n->timing === '2h'
        );

        $this->assertNotNull($booking->fresh()->rappel_2h_envoye_at);
    }

    public function test_urgent_pending_alert_notifies_admins(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));

        $admin = User::factory()->admin()->create();

        $booking = Booking::factory()->create([
            'status' => 'en_attente',
            'priorite' => 'urgente',
            'alerte_urgence_envoyee_at' => null,
            'created_at' => now()->subHours(3),
        ]);

        Artisan::call('app:send-rendezvous-reminders');

        Notification::assertSentTo($admin, UrgenceRendezVousNotification::class);

        $this->assertNotNull($booking->fresh()->alerte_urgence_envoyee_at);
    }

    public function test_overrun_alert_digest_is_sent_to_admins(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));

        $admin = User::factory()->admin()->create();

        // Same catalog for all three so they group under one service_display_name.
        $catalog = ServiceCatalog::factory()->create(['name' => 'Nettoyage Bureau']);

        foreach (range(1, 3) as $i) {
            $booking = Booking::factory()->create([
                'status' => 'termine',
                'service_catalog_id' => $catalog->id,
                'mission_finished_at' => now()->subDay(),
                'estimated_duration_minutes' => 60,
            ]);
            $booking->duree_reelle = 120;
            $booking->save();
        }

        Artisan::call('app:send-rendezvous-reminders');

        Notification::assertSentTo(
            $admin,
            AdminDigestNotification::class,
            fn (AdminDigestNotification $n) => $n->title === 'Synthèse des dépassements de durée'
        );
    }

    public function test_low_feedback_rate_digest_is_sent_to_admins(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));

        $admin = User::factory()->admin()->create();

        // Finished missions with no feedback => 0% rate < 40% threshold.
        Booking::factory()->count(2)->create([
            'status' => 'termine',
            'mission_finished_at' => now()->subDays(2),
        ]);

        Artisan::call('app:send-rendezvous-reminders');

        Notification::assertSentTo(
            $admin,
            AdminDigestNotification::class,
            fn (AdminDigestNotification $n) => $n->title === 'Synthèse qualité feedback'
        );
    }
}
