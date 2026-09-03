<?php

namespace Tests\Feature\PeerRental;

use App\Livewire\PeerRental\PeerMyRentals;
use App\Models\PeerRental;
use App\Models\PeerStay;
use App\Models\PeerVehicle;
use App\Models\User;
use App\Services\PeerRental\PeerRentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LES ECRANS PARTAGES RENDENT LES DEUX BIENS.
 *
 * Le contrat de location est le meme pour une voiture et pour un logement : c'est tout l'interet
 * de n'ecrire le chemin de l'argent qu'une fois. Mais les ECRANS de ce chemin lisaient encore
 * `vehicle` — nul pour un sejour. La page d'une location plantait sur son propre titre, et le
 * bareme d'annulation lisait une propriete d'un objet absent.
 *
 * Chaque cas porte donc son temoin vehicule : ce qui marchait doit continuer de marcher.
 */
class LeCheminPartageRendLesDeuxBiensTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_d_une_location_de_logement_s_affiche(): void
    {
        $logement = PeerStay::factory()->publiee()->create(['title' => 'Loft du canal']);
        $location = PeerRental::factory()->pourUnLogement($logement)->confirmee()->create();

        $this->actingAs($location->renter)
            ->get(route('peer.rental', $location))
            ->assertOk()
            ->assertSee('Loft du canal');
    }

    /** TEMOIN — la meme page pour un vehicule, inchangee. */
    public function test_temoin_la_page_d_une_location_de_vehicule_s_affiche(): void
    {
        $vehicule = PeerVehicle::factory()->publiee()->create(['brand' => 'Renault', 'model' => 'Zoe']);
        $location = PeerRental::factory()->confirmee()->create([
            'peer_vehicle_id' => $vehicule->id,
            'owner_id' => $vehicule->owner_id,
        ]);

        $this->actingAs($location->renter)
            ->get(route('peer.rental', $location))
            ->assertOk()
            ->assertSee('Renault Zoe');
    }

    public function test_la_liste_des_locations_nomme_un_logement(): void
    {
        $logement = PeerStay::factory()->publiee()->create(['title' => 'Loft du canal']);
        $location = PeerRental::factory()->pourUnLogement($logement)->create();

        Livewire::actingAs($location->renter)->test(PeerMyRentals::class)
            ->assertSee('Loft du canal')
            ->assertDontSee($location->reference);
    }

    /** TEMOIN — la meme liste nomme toujours un vehicule. */
    public function test_temoin_la_liste_nomme_toujours_un_vehicule(): void
    {
        $vehicule = PeerVehicle::factory()->publiee()->create(['brand' => 'Renault', 'model' => 'Zoe']);
        $location = PeerRental::factory()->create([
            'peer_vehicle_id' => $vehicule->id,
            'owner_id' => $vehicule->owner_id,
        ]);

        Livewire::actingAs($location->renter)->test(PeerMyRentals::class)
            ->assertSee('Renault Zoe');
    }

    /**
     * LE BAREME D'ANNULATION VIENT DU BIEN.
     *
     * Il lisait `vehicle->cancellation_policy` : sur un sejour, l'annulation — le moment ou
     * l'argent bouge — levait une erreur fatale au lieu de retenir des frais.
     */
    public function test_les_frais_d_annulation_se_calculent_pour_un_logement(): void
    {
        $logement = PeerStay::factory()->publiee()->create(['cancellation_policy' => 'stricte']);

        $location = PeerRental::factory()->pourUnLogement($logement)->confirmee()->create([
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addDays(2),
            'total_cents' => 20000,
        ]);

        // A deux heures du depart, aucun palier n'est atteint : la retenue est totale.
        $this->assertSame(20000, app(PeerRentalService::class)->fraisDAnnulation($location));
    }

    /** TEMOIN — loin du depart, le meme bareme ne retient rien. */
    public function test_temoin_loin_du_depart_le_bareme_ne_retient_rien(): void
    {
        $logement = PeerStay::factory()->publiee()->create(['cancellation_policy' => 'souple']);

        $location = PeerRental::factory()->pourUnLogement($logement)->confirmee()->create([
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(33),
            'total_cents' => 20000,
        ]);

        $this->assertSame(0, app(PeerRentalService::class)->fraisDAnnulation($location));
    }

    /**
     * UN BAREME INCONNU NE VAUT PAS « ON GARDE TOUT ».
     *
     * Le logement naissait avec `flexible`, un mot que `config/peer_rental.cancellation` ignore :
     * aucun palier ne correspondait, et la retenue tombait a 100 % du loyer, en silence.
     */
    public function test_un_bareme_inconnu_ne_retient_pas_tout_le_loyer(): void
    {
        $logement = PeerStay::factory()->publiee()->create();
        $logement->forceFill(['cancellation_policy' => 'flexible'])->save();

        $location = PeerRental::factory()->pourUnLogement($logement)->confirmee()->create([
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(33),
            'total_cents' => 20000,
        ]);

        $this->assertSame(0, app(PeerRentalService::class)->fraisDAnnulation($location));
    }

    /** LE PROPRIETAIRE VOIT SA DEMANDE, du cote « je prete ». */
    public function test_le_proprietaire_voit_la_demande_sur_son_logement(): void
    {
        $proprietaire = User::factory()->create();
        $logement = PeerStay::factory()->publiee()->create([
            'owner_id' => $proprietaire->id,
            'title' => 'Loft du canal',
        ]);

        PeerRental::factory()->pourUnLogement($logement)->create();

        Livewire::actingAs($proprietaire)->test(PeerMyRentals::class)
            ->set('role', 'owner')
            ->assertSee('Loft du canal');
    }
}
