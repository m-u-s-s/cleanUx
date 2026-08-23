<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Feedback;
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
        $rdv = Booking::factory()->create([
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

        // Toutes les notifications fautives d'un coup : un libelle de service qui derive derive
        // sur TOUTE la famille, et le destinataire recoit alors plusieurs messages incoherents.
        $defauts = [];

        foreach ($notifications as $notification) {
            $nom = class_basename($notification);

            if (! in_array('database', $notification->via($admin), true)) {
                $defauts[] = "{$nom} : pas de canal « database »";
            }

            if (! $notification->toMail($admin) instanceof MailMessage) {
                $defauts[] = "{$nom} : toMail() ne rend pas un MailMessage";
            }

            $payload = $notification->toArray($admin);

            if (! is_array($payload)) {
                $defauts[] = "{$nom} : toArray() ne rend pas un tableau";

                continue;
            }

            foreach ([
                'service_label' => $rdv->service_display_name,
                'location_display' => $rdv->location_display,
            ] as $clef => $attendu) {
                if (array_key_exists($clef, $payload) && $payload[$clef] !== $attendu) {
                    $defauts[] = sprintf('%s : %s vaut « %s », attendu « %s »', $nom, $clef, $payload[$clef], $attendu);
                }
            }
        }

        $this->assertSame([], $defauts, 'Ces notifications ne portent pas ce qu elles annoncent.');

        $feedbackNotification = new FeedbackAjouteNotification($feedback);
        $this->assertSame(['database'], $feedbackNotification->via($admin));
        $payload = $feedbackNotification->toDatabase($admin);
        $this->assertSame($feedback->id, $payload['feedback_id']);
        $this->assertSame('Client Test', $payload['client']);
    }
}
