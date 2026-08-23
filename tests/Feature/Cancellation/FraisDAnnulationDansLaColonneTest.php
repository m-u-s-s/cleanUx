<?php

namespace Tests\Feature\Cancellation;

use App\Livewire\Admin\Analytics\CancellationReasonsCenter;
use App\Models\Booking;
use App\Models\User;
use App\Services\Cancellation\CancelBookingService;
use App\Services\CancellationV2\CancellationEngine;
use Carbon\Carbon;
use Database\Seeders\CancellationPoliciesSeeder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LES FRAIS D'ANNULATION ARRIVENT-ILS JUSQU'À LA COLONNE QUI LES ADDITIONNE ?
 *
 * `bookings.cancellation_fee_amount` existe depuis la création de la table. Le centre d'analyse
 * des annulations en fait `SUM(COALESCE(cancellation_fee_amount, 0))`. Personne ne l'écrivait :
 * les frais n'existaient que sous une clé du `metadata` (chemin v1) ou dans
 * `booking_cancellations_v2` (chemin v2). L'écran annonçait donc **0 € de frais perçus**, pour
 * toutes les annulations, depuis toujours.
 *
 * ── POURQUOI LE TEST EXISTANT NE POUVAIT PAS LE VOIR ─────────────────────────────────────────
 *
 * `CancellationReasonsCenterCoverageBatch15Test` posait la valeur lui-même :
 *
 *     // cancellation_fee_amount is guarded; set it directly to exercise the SUM().
 *     $booking->forceFill(['cancellation_fee_amount' => 25.00])->save();
 *
 * Il prouvait que le `SUM()` sait additionner. Il ne pouvait rien dire de la seule question qui
 * comptait — est-ce que quelque chose remplit cette colonne ? — et il n'affirmait d'ailleurs
 * jamais le total obtenu. Les tests ci-dessous passent donc par le SERVICE RÉEL.
 *
 * ── L'UNITÉ, SECOND DÉFAUT EMPILÉ SUR LE PREMIER ─────────────────────────────────────────────
 *
 * La colonne est un `decimal(10,2)` en euros, mais la somme s'appelait `total_fee_cents` et la
 * vue la divisait par cent. Tant que la colonne restait vide, 0/100 valait 0 et l'erreur ne
 * pouvait pas se manifester. Les deux défauts se protégeaient l'un l'autre.
 */
class FraisDAnnulationDansLaColonneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * TÉMOIN POSITIF — le chemin d'annulation client remplit bien les deux colonnes.
     *
     * Sans ce contrôle, les tests d'affichage ci-dessous pourraient passer au vert en mesurant
     * un écran qui additionne correctement des zéros.
     */
    public function test_temoin_l_annulation_client_ecrit_les_deux_colonnes(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        $client = User::factory()->create();
        $reservation = $this->creerReservation([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '11:00:00', // une heure avant → palier à 50 %
            'estimated_price' => 100,
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'created_at' => now()->subHours(48),
        ]);

        app(CancelBookingService::class)->cancelByClient($reservation, $client, 'changement de plans');

        $frais = $reservation->fresh();

        /*
         * COMPARAISON NUMÉRIQUE, PAS TEXTUELLE : la suite tourne sur SQLite, qui n'a pas de type
         * décimal et rend « 50 » là où MySQL rend « 50.00 ». Une assertion sur la chaîne mesurerait
         * le moteur de base, pas le service.
         */
        $this->assertEqualsWithDelta(50.0, (float) $frais->cancellation_fee_amount, 0.001, 'Le montant est en EUROS.');
        $this->assertSame(50, (int) $frais->cancellation_fee_percent);
    }

    /**
     * LE `metadata` N'A PAS BOUGÉ.
     *
     * Des réponses d'API et d'autres tests le lisent. La question posée était de remplir la
     * colonne, pas de déplacer la notion : déplacer aurait cassé ces lecteurs-là.
     */
    public function test_le_metadata_continue_de_porter_les_frais(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        $client = User::factory()->create();
        $reservation = $this->creerReservation([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '11:00:00',
            'estimated_price' => 100,
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'created_at' => now()->subHours(48),
        ]);

        app(CancelBookingService::class)->cancelByClient($reservation, $client, 'changement de plans');

        $metadata = $reservation->fresh()->metadata ?? [];

        $this->assertEqualsWithDelta(50.0, (float) $metadata['cancellation_fee'], 0.001);
        $this->assertSame(50, (int) $metadata['cancellation_fee_percent']);
    }

    /**
     * L'ÉCRAN D'ADMINISTRATION ANNONCE LE MONTANT RÉEL, EN EUROS.
     *
     * 50 €, pas 0 € (colonne vide) et pas 0,50 € (division par cent héritée de l'alias menteur).
     */
    public function test_le_centre_d_analyse_totalise_les_frais_en_euros(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        $client = User::factory()->create();
        $reservation = $this->creerReservation([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '11:00:00',
            'estimated_price' => 100,
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'created_at' => now()->subHours(48),
        ]);

        app(CancelBookingService::class)->cancelByClient($reservation, $client, 'changement de plans');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(CancellationReasonsCenter::class)
            ->assertViewHas('rows', function ($lignes) {
                $ligne = $lignes->firstWhere('cancellation_reason', 'changement de plans');

                return $ligne !== null && abs(((float) $ligne->total_fee_euros) - 50.0) < 0.001;
            });
    }

    /**
     * LES COLONNES RESTENT HORS DE L'ASSIGNATION EN MASSE — ET C'EST VOULU.
     *
     * C'est la convention du dépôt pour l'argent : une charge utile de requête ne doit pas
     * pouvoir fixer un montant de frais. Ce test fixe la raison pour laquelle le service emploie
     * `forceFill` : s'il passait par `update()`, les deux colonnes n'arriveraient jamais en base.
     *
     * Le refus est BRUYANT ici et SILENCIEUX en production — `Model::preventSilentlyDiscarding`
     * n'est armé que hors production. C'est justement pourquoi le défaut d'origine pouvait durer :
     * en production, un `update()` sur ces colonnes ne se plaint de rien.
     */
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

    /**
     * LE CHEMIN v2 ÉTAIT ENTIÈREMENT INVISIBLE À L'ÉCRAN.
     *
     * `CancellationEngine` écrit tout son détail dans `booking_cancellations_v2` — et c'est bien
     * sa place. Mais il ne posait NI `cancellation_reason` NI `cancelled_by` sur la réservation,
     * or le centre d'analyse filtre sur le premier et groupe sur le second. Aucune annulation
     * passée par la v2 n'apparaissait dans cet écran, et rien ne le signalait : une liste vide
     * ressemble à « personne n'a annulé ».
     */
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
