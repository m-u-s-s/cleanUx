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

/** CINQ COLONNES POUR UNE SEULE HEURE, ET LA REPROGRAMMATION N'EN DÉPLAÇAIT QUE DEUX. */
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

    /** LE TÉMOIN POSITIF, et il porte sur la création. */
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

    /** LA CONSÉQUENCE QUI SE VOIT DE L'EXTÉRIEUR. */
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

    /** QUAND L'APPELANT ÉCRIT LES DEUX CÔTÉS, ON NE DEVINE PAS À SA PLACE. */
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
