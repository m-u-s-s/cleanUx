<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\MesRendezVousClient;
use Database\Seeders\CancellationPoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\CreatesMissionPortalFixtures;
use Tests\TestCase;

/**
 * LES FRAIS D'ANNULATION SE DISENT AVANT, PAS SUR LE RELEVE.
 *
 * La modale demandait « confirmer ? », le moteur prelevait, et le client apprenait le montant
 * apres coup. Quinze lignes de JavaScript avaient ete COLLEES dans la vue pour interroger la
 * route de devis — hors de toute balise `<script>`, donc affichees en clair au client, motif
 * d'en-tete `Authorization: Bearer` compris.
 *
 * Le devis est desormais calcule cote serveur, ou nous sommes deja.
 */
class FraisAnnoncesAvantAnnulationTest extends TestCase
{
    use CreatesMissionPortalFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CancellationPoliciesSeeder::class);
        Config::set('cancellation_v2.enabled', true);
        Config::set('cancellation_v2.default_refund_method', 'mock');
        Config::set('cancellation_v2.integrations.stripe_refund', false);
        Config::set('cancellation_v2.integrations.insurance_cancel', false);

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '50.8466', 'lon' => '4.3528', 'display_name' => 'Rue de Test 1, 1000 Bruxelles'],
            ], 200),
        ]);
    }

    public function test_la_modale_ne_rend_plus_le_javascript_qui_y_etait_colle(): void
    {
        $scenario = $this->createMissionPortalContext();

        $this->actingAs($scenario['client']);

        $rendu = Livewire::test(MesRendezVousClient::class)
            ->call('demanderAnnulation', $scenario['rendezVous']->id)
            ->html();

        // Le client voyait ces trois fragments en clair, dans la modale.
        $this->assertStringNotContainsString('Authorization', $rendu);
        $this->assertStringNotContainsString('cancellation-quote', $rendu);
        $this->assertStringNotContainsString('await fetch', $rendu);
    }

    public function test_la_modale_annonce_un_montant_ou_dit_qu_il_n_y_en_a_pas(): void
    {
        $scenario = $this->createMissionPortalContext();

        $this->actingAs($scenario['client']);

        $rendu = Livewire::test(MesRendezVousClient::class)
            ->call('demanderAnnulation', $scenario['rendezVous']->id)
            ->html();

        /*
         * L'un ou l'autre, jamais rien. Figer LEQUEL ferait dependre ce test du bareme de la
         * base de demonstration : ce qui compte est que le client sache a quoi s'en tenir
         * avant de cliquer, pas quel palier s'applique dans cette fixture.
         */
        $this->assertMatchesRegularExpression(
            '/frais de|sans frais|peuvent s\Wappliquer/u',
            $rendu,
        );
    }

    /**
     * TEMOIN — le devis est bien calcule, pas devine.
     *
     * Sans ce controle, une modale qui afficherait toujours « des frais peuvent s'appliquer »
     * passerait le test precedent en n'ayant rien demande a personne.
     */
    public function test_temoin_le_devis_est_reellement_etabli(): void
    {
        $scenario = $this->createMissionPortalContext();

        $this->actingAs($scenario['client']);

        $devis = Livewire::test(MesRendezVousClient::class)
            ->call('demanderAnnulation', $scenario['rendezVous']->id)
            ->instance()
            ->devisAnnulation();

        $this->assertNotNull($devis);
        $this->assertSame($scenario['rendezVous']->id, $devis->bookingId);
        $this->assertNotSame('', $devis->currency);
    }

    /** TEMOIN — sans rendez-vous choisi, aucun devis n'est demande. */
    public function test_temoin_aucun_devis_hors_annulation(): void
    {
        $scenario = $this->createMissionPortalContext();

        $this->actingAs($scenario['client']);

        $this->assertNull(
            Livewire::test(MesRendezVousClient::class)->instance()->devisAnnulation()
        );
    }
}
