<?php

namespace Tests\Feature\Cancellation;

use App\Livewire\Admin\Analytics\CancellationReasonsCenter;
use App\Models\Booking;
use App\Models\User;
use App\Services\CancellationV2\CancellationEngine;
use Carbon\Carbon;
use Database\Seeders\CancellationPoliciesSeeder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** LES FRAIS D'ANNULATION ARRIVENT-ILS JUSQU'À LA COLONNE QUI LES ADDITIONNE ? */
class FraisDAnnulationDansLaColonneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** TÉMOIN POSITIF — le chemin d'annulation client remplit bien les deux colonnes. */
    public function test_temoin_l_annulation_client_ecrit_les_deux_colonnes(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        $client = User::factory()->create();
        $reservation = $this->creerReservation([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '11:00:00', // une heure avant → palier à 50 %
            'scheduled_at' => '2026-05-14 11:00:00',
            'estimated_price' => 100,
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'created_at' => now()->subHours(48),
        ]);

        app(CancellationEngine::class)->execute(
            bookingId: $reservation->id,
            actor: $client,
            actorRole: 'client',
            reasonText: 'changement de plans',
        );

        $frais = $reservation->fresh();

        // COMPARAISON NUMÉRIQUE, PAS TEXTUELLE : la suite tourne sur SQLite, qui n'a pas de type décimal et rend « 50 » là où MySQL rend « 50.00 ».
        $this->assertEqualsWithDelta(50.0, (float) $frais->cancellation_fee_amount, 0.001, 'Le montant est en EUROS.');
        $this->assertSame(50, (int) $frais->cancellation_fee_percent);
    }

    /** LE `metadata` N'A PAS BOUGÉ. Des réponses d'API et d'autres tests le lisent. */
    public function test_le_metadata_continue_de_porter_les_frais(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        $client = User::factory()->create();
        $reservation = $this->creerReservation([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '11:00:00',
            'scheduled_at' => '2026-05-14 11:00:00',
            'estimated_price' => 100,
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'created_at' => now()->subHours(48),
        ]);

        app(CancellationEngine::class)->execute(
            bookingId: $reservation->id,
            actor: $client,
            actorRole: 'client',
            reasonText: 'changement de plans',
        );

        $metadata = $reservation->fresh()->metadata ?? [];

        $this->assertEqualsWithDelta(50.0, (float) $metadata['cancellation_fee'], 0.001);
        $this->assertSame(50, (int) $metadata['cancellation_fee_percent']);
    }

    /** L'ÉCRAN D'ADMINISTRATION ANNONCE LE MONTANT RÉEL, EN EUROS. */
    public function test_le_centre_d_analyse_totalise_les_frais_en_euros(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        $client = User::factory()->create();
        $reservation = $this->creerReservation([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '11:00:00',
            'scheduled_at' => '2026-05-14 11:00:00',
            'estimated_price' => 100,
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'created_at' => now()->subHours(48),
        ]);

        app(CancellationEngine::class)->execute(
            bookingId: $reservation->id,
            actor: $client,
            actorRole: 'client',
            reasonText: 'changement de plans',
        );

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(CancellationReasonsCenter::class)
            ->assertViewHas('rows', function ($lignes) {
                $ligne = $lignes->firstWhere('cancellation_reason', 'changement de plans');

                return $ligne !== null && abs(((float) $ligne->total_fee_euros) - 50.0) < 0.001;
            });
    }

    /** LES COLONNES RESTENT HORS DE L'ASSIGNATION EN MASSE — ET C'EST VOULU. */
    public function test_les_colonnes_d_argent_resistent_a_l_assignation_en_masse(): void
    {
        $reservation = $this->creerReservation();

        $this->expectException(MassAssignmentException::class);

        try {
            $reservation->update([
                'cancellation_fee_amount' => 999.99,
                'cancellation_fee_percent' => 99,
            ]);
        } finally {
            // Contrôle positif : la colonne EST écrivable par le chemin prévu, `forceFill`.
            $reservation->forceFill(['cancellation_fee_amount' => 12.34])->save();
            $this->assertEqualsWithDelta(12.34, (float) $reservation->fresh()->cancellation_fee_amount, 0.001);
        }
    }

    /** LE CHEMIN v2 ÉTAIT ENTIÈREMENT INVISIBLE À L'ÉCRAN. */
    public function test_une_annulation_v2_devient_visible_dans_le_centre_d_analyse(): void
    {
        $this->seed(CancellationPoliciesSeeder::class);
        Config::set('cancellation_v2.enabled', true);
        Config::set('cancellation_v2.default_refund_method', 'mock');
        Config::set('cancellation_v2.integrations.stripe_refund', false);
        Config::set('cancellation_v2.integrations.insurance_cancel', false);

        $client = User::factory()->client()->create();
        $quand = now()->addHours(30);   // entre 24 et 48 h → palier à 25 %

        $reservation = Booking::create([
            'client_id' => $client->id,
            'date' => $quand,
            'heure' => $quand->format('H:i'),
            'scheduled_at' => $quand,
            'status' => 'confirme',
            'devis_estime' => 100.0,
        ]);

        // Hors de la fenetre de grace : le sujet du test est le palier a 25 %, pas la grace.
        $reservation->forceFill(['created_at' => now()->subHour()])->save();

        app(CancellationEngine::class)->execute(
            bookingId: $reservation->id,
            actor: $client,
            actorRole: 'client',
            reasonCode: 'client_unavailable',
        );

        $frais = $reservation->fresh();

        $this->assertNotNull($frais->cancellation_reason, 'Sans motif, la v2 reste hors de l’écran.');
        $this->assertSame($client->id, (int) $frais->cancelled_by);
        $this->assertEqualsWithDelta(25.0, (float) $frais->cancellation_fee_amount, 0.001);
        $this->assertSame(25, (int) $frais->cancellation_fee_percent);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(CancellationReasonsCenter::class)
            ->assertViewHas('totalCancelled', 1)
            ->assertViewHas('rows', fn ($lignes) => $lignes->count() === 1
                && abs(((float) $lignes->first()->total_fee_euros) - 25.0) < 0.001);
    }

    protected function creerReservation(array $surcharges = []): Booking
    {
        $client = User::factory()->create();

        return Booking::create(array_merge([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'estimated_price' => 100,
        ], $surcharges));
    }
}
