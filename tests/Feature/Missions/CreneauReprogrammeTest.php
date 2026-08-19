<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\User;
use App\Services\Cancellation\CancellationAnswerVerifier;
use App\Services\Client\Calendar\BookingRescheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CINQ COLONNES POUR UNE SEULE HEURE, ET LA REPROGRAMMATION N'EN DÉPLAÇAIT QUE DEUX.
 *
 * `bookings` porte `date`, `heure`, `scheduled_date`, `scheduled_time` et `scheduled_at`. Un trait
 * les tenait d'accord — mais seulement en COMBLANT LES TROUS. Tant qu'on ne faisait que créer des
 * réservations, l'illusion tenait : les cinq colonnes finissaient toujours identiques.
 *
 * À la première modification, elles divergeaient définitivement. `BookingRescheduleService` écrit
 * `scheduled_date` et `scheduled_time` ; les trois autres étaient déjà remplies, donc rien ne se
 * propageait. Mesuré avant correction : un rendez-vous du 10 septembre 10 h déplacé au 12 à 14 h
 * gardait `date = 2026-09-10`, `heure = 10:00:00` et `scheduled_at = 2026-09-10 10:00:00`.
 *
 * Ce n'est pas cosmétique. Le barème d'annulation lit `scheduled_at` en premier : un client qui
 * décale son rendez-vous d'une semaine, puis l'annule, était facturé au palier « moins de 24 h »
 * calculé contre le créneau qu'il avait justement abandonné.
 */
class CreneauReprogrammeTest extends TestCase
{
    use RefreshDatabase;

    private function reservation(User $client, array $ecrasements = []): Booking
    {
        return Booking::create(array_merge([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'scheduled_date' => '2026-09-10',
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ], $ecrasements));
    }

    /**
     * LE TÉMOIN POSITIF, et il porte sur la création.
     *
     * Avant correction, `scheduled_at` restait NULLE au premier enregistrement : le trait la
     * calculait avant d'avoir recopié `scheduled_date` dans `date`, donc à partir de colonnes
     * encore vides. Elle ne se remplissait qu'à la sauvegarde suivante — s'il y en avait une.
     */
    #[Test]
    public function la_creation_remplit_les_cinq_colonnes_du_premier_coup(): void
    {
        $client = User::factory()->create();

        $booking = $this->reservation($client)->fresh();

        $this->assertSame('2026-09-10', $booking->date?->toDateString());
        $this->assertStringStartsWith('10:00', (string) $booking->heure);
        $this->assertSame('2026-09-10 10:00:00', $booking->scheduled_at?->toDateTimeString());
    }

    /** Le cœur du défaut : la reprogrammation doit déplacer TOUT le créneau. */
    #[Test]
    public function la_reprogrammation_deplace_les_cinq_colonnes(): void
    {
        $client = User::factory()->create();
        $booking = $this->reservation($client);

        app(BookingRescheduleService::class)->reschedule($client, $booking, Carbon::parse('2026-09-12'), '14:00');

        $apres = $booking->fresh();

        $this->assertSame('2026-09-12', $apres->date?->toDateString());
        $this->assertStringStartsWith('14:00', (string) $apres->heure);
        $this->assertSame('2026-09-12 14:00:00', $apres->scheduled_at?->toDateTimeString());
        $this->assertSame('2026-09-12', $apres->scheduled_date?->toDateString());
    }

    /**
     * LA CONSÉQUENCE QUI SE VOIT DE L'EXTÉRIEUR.
     *
     * Décaler un rendez-vous vers le futur doit éteindre le retard. Sans la propagation, le
     * minuteur aurait continué de lire l'ancienne heure — et annoncé au client un retard sur une
     * intervention qu'il venait lui-même de repousser.
     */
    #[Test]
    public function reprogrammer_vers_le_futur_eteint_le_retard(): void
    {
        $client = User::factory()->create();
        $booking = $this->reservation($client, [
            'scheduled_date' => Carbon::now()->subHours(2)->toDateString(),
            'scheduled_time' => Carbon::now()->subHours(2)->format('H:i:s'),
        ]);

        $verificateur = app(CancellationAnswerVerifier::class);

        // Le témoin : il EST en retard tant qu'on n'a rien déplacé.
        $this->assertTrue($verificateur->leProviderEstEnRetard($booking->fresh()));

        app(BookingRescheduleService::class)->reschedule(
            $client,
            $booking,
            Carbon::now()->addDays(3),
            '09:00'
        );

        $this->assertFalse($verificateur->leProviderEstEnRetard($booking->fresh()));
    }

    /**
     * QUAND L'APPELANT ÉCRIT LES DEUX CÔTÉS, ON NE DEVINE PAS À SA PLACE.
     *
     * Une bonne partie du dépôt crée des réservations en renseignant le couple français ET le
     * couple anglais. Départager sur la fraîcheur écraserait alors l'un des deux au hasard de
     * l'ordre du tableau : quand les deux ont changé, on ne touche à rien.
     */
    #[Test]
    public function ecrire_les_deux_cotes_ensemble_reste_respecte(): void
    {
        $client = User::factory()->create();

        $booking = $this->reservation($client, [
            'date' => '2026-09-10',
            'heure' => '10:00:00',
        ])->fresh();

        $this->assertSame('2026-09-10', $booking->date?->toDateString());
        $this->assertSame('2026-09-10', $booking->scheduled_date?->toDateString());
    }
}
