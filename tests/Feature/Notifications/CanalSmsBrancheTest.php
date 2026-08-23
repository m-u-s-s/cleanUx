<?php

namespace Tests\Feature\Notifications;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use App\Notifications\EmployeEnRouteNotification;
use App\Notifications\RappelRendezVousNotification;
use App\Services\NotificationPreferences\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** LE CANAL SMS PART VRAIMENT — et la matrice de préférences décide enfin de quelque chose. */
class CanalSmsBrancheTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        return User::factory()->client()->create(['phone' => '+32470111222']);
    }

    private function smsEnvoyes(): int
    {
        return DB::table('sms_messages')->count();
    }

    #[Test]
    public function le_rappel_de_rendez_vous_part_par_sms(): void
    {
        $client = $this->client();
        $booking = Booking::factory()->create(['client_id' => $client->id]);

        $client->notify(new RappelRendezVousNotification($booking, '24h'));

        // Le pilote de développement n'envoie rien : il ENREGISTRE. Le registre est donc la seule
        // preuve qu'un message est parti.
        $this->assertGreaterThan(0, $this->smsEnvoyes());
    }

    #[Test]
    public function le_refus_de_l_utilisateur_est_respecte(): void
    {
        $client = $this->client();
        $booking = Booking::factory()->create(['client_id' => $client->id]);

        // `booking_reminder` relève de la catégorie `reminder`, que l'utilisateur a le droit de
        // couper — contrairement au transactionnel légal.
        app(NotificationPreferenceService::class)->setPreference($client, 'sms', 'reminder', false);

        $client->notify(new RappelRendezVousNotification($booking, '24h'));

        // C'EST L'ASSERTION QUI COMPTE. Sans elle, on prouverait seulement qu'un SMS part — ce qui
        // était déjà vrai avant, pour les notifications qui appelaient le service en direct. Ce qui
        // manquait, c'est que le choix de l'utilisateur soit lu.
        $this->assertSame(0, $this->smsEnvoyes());
    }

    #[Test]
    public function le_prestataire_en_route_previent_par_sms(): void
    {
        $client = $this->client();
        // Une réservation `confirme` réveille l'observateur, qui crée la mission : on la retrouve
        // au lieu d'en fabriquer une seconde.
        $booking = Booking::factory()->create(['client_id' => $client->id, 'status' => 'confirme']);
        $mission = Mission::query()->where('booking_id', $booking->id)->firstOrFail();

        $client->notify(new EmployeEnRouteNotification($mission));

        $this->assertGreaterThan(0, $this->smsEnvoyes());
    }

    #[Test]
    public function un_client_sans_telephone_ne_bloque_rien(): void
    {
        $client = User::factory()->client()->create(['phone' => null]);
        $booking = Booking::factory()->create(['client_id' => $client->id]);

        // Le canal rend simplement `null` : une notification ne doit pas échouer entière parce
        // qu'un de ses canaux n'a pas de destinataire.
        $client->notify(new RappelRendezVousNotification($booking, '2h'));

        $this->assertSame(0, $this->smsEnvoyes());
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $client->id]);
    }

    #[Test]
    public function deux_rappels_du_meme_creneau_ne_font_qu_un_sms(): void
    {
        $client = $this->client();
        $booking = Booking::factory()->create(['client_id' => $client->id]);

        $client->notify(new RappelRendezVousNotification($booking, '24h'));
        $client->notify(new RappelRendezVousNotification($booking, '24h'));

        // Le plafond du module est de cinq messages par heure et par numéro : sans clé
        // d'idempotence, une file rejouée l'épuiserait et priverait le client des codes suivants.
        $this->assertSame(1, $this->smsEnvoyes());
    }
}
