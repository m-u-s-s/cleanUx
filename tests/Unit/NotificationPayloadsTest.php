<?php

namespace Tests\Unit;

use App\Models\Feedback;
use App\Models\RendezVous;
use App\Models\User;
use App\Notifications\AdminDigestNotification;
use App\Notifications\DemandeFeedbackNotification;
use App\Notifications\EmployeReaffectationSuggestionNotification;
use App\Notifications\FeedbackAjouteNotification;
use App\Notifications\MissionReplanifieeNotification;
use App\Notifications\NouveauRendezVousNotification;
use App\Notifications\RappelRendezVousNotification;
use App\Notifications\RdvConfirmeNotification;
use App\Notifications\StatutRendezVousNotification;
use App\Notifications\UrgenceRendezVousNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class NotificationPayloadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_and_database_notifications_expose_expected_payloads(): void
    {
        $client = User::factory()->client()->create(['name' => 'Client Test']);
        $employe = User::factory()->employe()->create(['name' => 'Employé Test']);
        $admin = User::factory()->admin()->create();
        $rdv = RendezVous::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $employe->id,
            'status' => 'confirme',
            'priorite' => 'urgente',
            'adresse' => 'Rue de Test 1',
            'ville' => 'Bruxelles',
            'heure' => '10:00:00',
        ]);
        $feedback = Feedback::factory()->forRendezVous($rdv)->create([
            'client_id' => $client->id,
        ]);

        $notifications = [
            new AdminDigestNotification(['Alerte 1', 'Alerte 2']),
            new DemandeFeedbackNotification($rdv),
            new EmployeReaffectationSuggestionNotification($rdv, 'Employé A', 'Employé B'),
            new MissionReplanifieeNotification($rdv, 'Employé A', '2026-01-01', '09:00:00'),
            new NouveauRendezVousNotification($rdv),
            new RappelRendezVousNotification($rdv, '24h'),
            new RdvConfirmeNotification($rdv),
            new StatutRendezVousNotification($rdv),
            new UrgenceRendezVousNotification($rdv),
        ];

        foreach ($notifications as $notification) {
            $this->assertContains('database', $notification->via($admin));
            $this->assertInstanceOf(MailMessage::class, $notification->toMail($admin));
            $payload = $notification->toArray($admin);
            $this->assertIsArray($payload);

            if (array_key_exists('service_label', $payload)) {
                $this->assertSame($rdv->service_display_name, $payload['service_label']);
            }

            if (array_key_exists('location_display', $payload)) {
                $this->assertSame($rdv->location_display, $payload['location_display']);
            }
        }

        $feedbackNotification = new FeedbackAjouteNotification($feedback);
        $this->assertSame(['database'], $feedbackNotification->via($admin));
        $payload = $feedbackNotification->toDatabase($admin);
        $this->assertSame($feedback->id, $payload['feedback_id']);
        $this->assertSame('Client Test', $payload['client']);
    }
}
