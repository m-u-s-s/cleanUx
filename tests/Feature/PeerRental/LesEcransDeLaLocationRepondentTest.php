<?php

namespace Tests\Feature\PeerRental;

use App\Models\PeerRental;
use App\Models\PeerVehicle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LES ECRANS EXISTENT ET S'OUVRENT.
 *
 * La famille de defauts dominante de ce depot, c'est le module complet et injoignable : le
 * service marche, la vue existe, et aucune porte n'y mene. Ce test ouvre chaque ecran par sa
 * route, avec le compte qui a le droit — et verifie que les autres se font refuser.
 */
class LesEcransDeLaLocationRepondentTest extends TestCase
{
    use RefreshDatabase;

    private function membre(): User
    {
        return User::factory()->client()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    public function test_la_vitrine_est_publique(): void
    {
        PeerVehicle::factory()->publiee()->create();

        $this->get(route('peer.catalogue'))->assertOk();
    }

    public function test_la_fiche_d_un_vehicule_publie_est_publique(): void
    {
        $vehicule = PeerVehicle::factory()->publiee()->create();

        $this->get(route('peer.vehicule', $vehicule))->assertOk();
    }

    /** Une annonce en brouillon n'est pas une page : elle n'existe pas encore pour le public. */
    public function test_une_annonce_en_brouillon_reste_invisible(): void
    {
        $vehicule = PeerVehicle::factory()->create();

        $this->get(route('peer.vehicule', $vehicule))->assertNotFound();
    }

    public function test_mes_locations_et_mes_vehicules_s_ouvrent(): void
    {
        $membre = $this->membre();

        $this->actingAs($membre)->get(route('peer.my-rentals'))->assertOk();
        $this->actingAs($membre)->get(route('peer.owner.vehicles'))->assertOk();
    }

    public function test_l_editeur_n_est_ouvert_qu_a_son_proprietaire(): void
    {
        $proprietaire = $this->membre();
        $autre = $this->membre();

        $vehicule = PeerVehicle::factory()->create(['owner_id' => $proprietaire->id]);

        $this->actingAs($proprietaire)->get(route('peer.owner.vehicle', $vehicule))->assertOk();
        $this->actingAs($autre)->get(route('peer.owner.vehicle', $vehicule))->assertForbidden();
    }

    public function test_la_location_n_est_ouverte_qu_a_ses_deux_parties(): void
    {
        $proprietaire = $this->membre();
        $locataire = $this->membre();
        $intrus = $this->membre();

        $vehicule = PeerVehicle::factory()->publiee()->create(['owner_id' => $proprietaire->id]);

        $location = PeerRental::factory()->create([
            'peer_vehicle_id' => $vehicule->id,
            'owner_id' => $proprietaire->id,
            'renter_id' => $locataire->id,
        ]);

        $this->actingAs($proprietaire)->get(route('peer.rental', $location))->assertOk();
        $this->actingAs($locataire)->get(route('peer.rental', $location))->assertOk();
        $this->actingAs($intrus)->get(route('peer.rental', $location))->assertForbidden();
    }

    public function test_le_centre_d_administration_est_reserve_aux_administrateurs(): void
    {
        // LA CAPACITE, PAS LE SEUL ROLE : `manage-peer-rentals` garde cet ecran comme
        // `manage-rentals` garde celui de la flotte maison. Un administrateur sans elle
        // ne doit pas y entrer — c'est justement ce que le second cas mesure.
        $arbitre = $this->prendreLeSiege([
            'role' => 'admin',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($arbitre)->get(route('peer.admin'))->assertOk();

        $this->actingAs($this->membre())->get(route('peer.admin'))->assertForbidden();
    }

    /** TEMOIN — les cases du registre mènent bien à ces routes. */
    public function test_le_registre_annonce_les_ecrans_du_module(): void
    {
        $routes = collect(config('modules.catalogue'))->pluck('route')->all();

        foreach (['peer.catalogue', 'peer.my-rentals', 'peer.owner.vehicles', 'peer.admin'] as $attendue) {
            $this->assertContains($attendue, $routes, "Le registre n’annonce pas {$attendue}");
        }
    }
}
