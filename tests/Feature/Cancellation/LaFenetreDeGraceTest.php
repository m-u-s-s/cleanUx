<?php

namespace Tests\Feature\Cancellation;

use App\Models\Booking;
use App\Models\User;
use App\Services\CancellationV2\CancellationEngine;
use Carbon\Carbon;
use Database\Seeders\CancellationPoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Se tromper de reservation et le voir tout de suite ne se facture pas.
 * Le service historique offrait cinq minutes ; le moteur n'en offrait aucune.
 */
class LaFenetreDeGraceTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $depart;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cancellation_v2.enabled', true);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '50.8', 'lon' => '4.3', 'display_name' => 'x']], 200)]);

        $this->depart = Carbon::parse('2026-09-10 10:00:00');
        $this->client = User::factory()->client()->create();
    }

    /** La reservation est prise `$avant` minutes avant le creneau. */
    private function reservation(int $avant = 60): Booking
    {
        Carbon::setTestNow($this->depart->copy()->subMinutes($avant));

        return Booking::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'confirme',
            'estimated_price' => 100.00,
            'date' => $this->depart->toDateString(),
            'heure' => '10:00:00',
            'scheduled_date' => $this->depart->toDateString(),
            'scheduled_time' => '10:00:00',
            'scheduled_at' => $this->depart->toDateTimeString(),
            'currency' => 'EUR',
        ]);
    }

    private function fraisApres(int $minutes, string $role = 'client', int $avant = 60): array
    {
        $rdv = $this->reservation($avant);
        $quand = $this->depart->copy()->subMinutes($avant)->addMinutes($minutes);
        Carbon::setTestNow($quand);

        $devis = app(CancellationEngine::class)->quote($rdv->id, $role, null, $quand->copy(), $this->client->id);

        Carbon::setTestNow();

        return ['frais' => $devis->feeAmountCents, 'avertissements' => $devis->warnings];
    }

    public function test_annuler_dans_la_minute_ne_coute_rien(): void
    {
        $this->seed(CancellationPoliciesSeeder::class);

        $mesure = $this->fraisApres(1);

        $this->assertSame(0, $mesure['frais']);
        $this->assertContains('fenetre_de_grace', $mesure['avertissements']);
    }

    /**
     * TEMOIN — passe la fenetre, le palier normal reprend la main. Sans ce controle, le test
     * ci-dessus resterait vert meme si la grace exonerait toutes les annulations.
     */
    public function test_temoin_passe_la_fenetre_le_palier_reprend(): void
    {
        $this->seed(CancellationPoliciesSeeder::class);

        $mesure = $this->fraisApres(10);

        $this->assertSame(10000, $mesure['frais'], 'A moins de 2 h du debut, la politique dit 100 %.');
        $this->assertNotContains('fenetre_de_grace', $mesure['avertissements']);
    }

    /** La grace vaut aussi quand aucune politique n'existe : le repli ne la contredit pas. */
    public function test_la_grace_prime_sur_le_bareme_de_repli(): void
    {
        $this->assertSame(0, $this->fraisApres(1)['frais']);
    }

    /**
     * Elle est offerte au client, pas au prestataire : sa penalite existe pour le desistement.
     * Mission prise 10 minutes avant le debut, donc DANS la fenetre a penalite du prestataire.
     */
    public function test_le_prestataire_n_a_pas_de_fenetre_de_grace(): void
    {
        $penalite = (int) round((float) config('cancellation.provider.penalty_eur') * 100);

        $this->assertSame($penalite, $this->fraisApres(1, 'provider', 10)['frais']);
        $this->assertSame(0, $this->fraisApres(1, 'client', 10)['frais'],
            'Le client, lui, garde sa fenetre de grace au meme instant.');
    }
}
