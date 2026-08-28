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
 * Une base sans politique facturait 0 EUR en silence, sur tous les chemins.
 * `config/cancellation.php` devient le filet du moteur : les tables priment, la config supplee.
 */
class LeBaremeDeRepliTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $depart;

    private User $client;

    private Booking $rdv;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cancellation_v2.enabled', true);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '50.8', 'lon' => '4.3', 'display_name' => 'x']], 200)]);

        $this->depart = Carbon::parse('2026-09-10 10:00:00');
        $this->client = User::factory()->client()->create();

        $this->rdv = Booking::factory()->create([
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

    private function fraisA(string $avant, string $role = 'client'): array
    {
        $quand = $this->depart->copy()->sub($avant);
        Carbon::setTestNow($quand);

        $devis = app(CancellationEngine::class)->quote($this->rdv->id, $role, null, $quand->copy(), $this->client->id);

        Carbon::setTestNow();

        return ['frais' => $devis->feeAmountCents, 'avertissements' => $devis->warnings, 'libelle' => $devis->tierLabel];
    }

    /** Sans politique en base, le moteur applique la grille de `config/cancellation.php`. */
    public function test_sans_politique_le_moteur_suit_la_configuration(): void
    {
        $attendu = [
            '48 hours' => 0,
            '25 hours' => 0,
            '23 hours' => 2500,
            '3 hours' => 2500,
            '1 hour' => 5000,
            '45 minutes' => 5000,
            '20 minutes' => 10000,
        ];

        foreach ($attendu as $avant => $cents) {
            $this->assertSame($cents, $this->fraisA($avant)['frais'],
                "Le repli ne rend pas le montant de la configuration a {$avant} du debut.");
        }
    }

    public function test_le_repli_se_signale_sur_le_devis(): void
    {
        $this->assertContains('politique_de_repli', $this->fraisA('1 hour')['avertissements']);
    }

    /**
     * TEMOIN — des qu'une politique existe, c'est ELLE qui prime, et le repli disparait.
     * Sans ce controle, le test ci-dessus passerait au vert meme si le repli ecrasait tout.
     */
    public function test_temoin_une_politique_en_base_prime_sur_le_repli(): void
    {
        $this->seed(CancellationPoliciesSeeder::class);

        $mesure = $this->fraisA('3 hours');

        // La politique dit 50 % entre 2 h et 24 h ; la configuration disait 25 %.
        $this->assertSame(5000, $mesure['frais']);
        $this->assertNotContains('politique_de_repli', $mesure['avertissements']);
    }

    /** Le prestataire aussi : sa penalite fixe est portee par le repli. */
    public function test_le_repli_porte_la_penalite_du_prestataire(): void
    {
        $penalite = (int) round((float) config('cancellation.provider.penalty_eur') * 100);

        $this->assertSame(0, $this->fraisA('3 hours', 'provider')['frais'],
            'Hors de la fenetre, le prestataire ne doit rien.');
        $this->assertSame($penalite, $this->fraisA('10 minutes', 'provider')['frais'],
            'Dans la fenetre, la penalite fixe de la configuration s\'applique.');
    }

    /** Le plancher de frais de la configuration est porte lui aussi. */
    public function test_le_plancher_de_frais_est_respecte(): void
    {
        Config::set('cancellation.client.minimum_fee_eur', 1.50);

        $this->assertSame(150, $this->fraisA('48 hours')['frais'],
            'Un palier a 0 % doit quand meme atteindre le plancher configure.');
    }
}
