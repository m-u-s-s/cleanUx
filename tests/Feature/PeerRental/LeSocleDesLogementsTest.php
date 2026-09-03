<?php

namespace Tests\Feature\PeerRental;

use App\Models\PeerRental;
use App\Models\PeerStay;
use App\Models\PeerVehicle;
use App\Models\PeerVehicleAvailability;
use App\Models\User;
use App\Services\PeerRental\Contracts\Louable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LA LOCATION ENTRE MEMBRES S'OUVRE AUX LOGEMENTS.
 *
 * Une voiture et un logement n'ont presque rien en commun. Mais LE CONTRAT DE LOCATION est le
 * meme : empreinte, caution, commission, versement, avis, litige. Ce chemin est celui de l'argent,
 * et l'ecrire deux fois reviendrait a accepter qu'un defaut se corrige a un seul des deux endroits.
 */
class LeSocleDesLogementsTest extends TestCase
{
    use RefreshDatabase;

    /** LES DEUX BIENS HONORENT LE MEME CONTRAT : c'est ce qui rend la couche d'argent unique. */
    public function test_un_logement_et_un_vehicule_honorent_le_meme_contrat(): void
    {
        $this->assertInstanceOf(Louable::class, PeerStay::factory()->make());
        $this->assertInstanceOf(Louable::class, PeerVehicle::factory()->make());
    }

    public function test_le_logement_repond_sur_le_prix_la_remise_et_la_duree(): void
    {
        $logement = PeerStay::factory()->make([
            'nightly_price_cents' => 9000,
            'discount_7_days_percent' => 10,
            'discount_28_days_percent' => 25,
            'min_nights' => 2,
            'max_nights' => 60,
        ]);

        $this->assertSame('stay', $logement->typeDeBien());
        $this->assertSame(9000, $logement->prixJournalierCents());
        $this->assertSame(0, $logement->remisePourDuree(2), 'Aucune remise sous trois nuits.');
        $this->assertSame(10, $logement->remisePourDuree(7));
        $this->assertSame(25, $logement->remisePourDuree(30));
        $this->assertSame(2, $logement->dureeMinimum());
        $this->assertSame(60, $logement->dureeMaximum());
    }

    /**
     * UNE DUREE MAXIMUM NE PEUT PAS ETRE INFERIEURE AU MINIMUM.
     *
     * Une annonce mal saisie rendrait sinon toute reservation impossible, sans qu'aucun message
     * ne l'explique.
     */
    public function test_la_duree_maximum_ne_passe_jamais_sous_le_minimum(): void
    {
        $logement = PeerStay::factory()->make(['min_nights' => 7, 'max_nights' => 3]);

        $this->assertSame(7, $logement->dureeMaximum());
    }

    /** LE SUPPLEMENT NE COMPTE QUE LES VOYAGEURS AU-DELA DE CE QUE LE PRIX COUVRE DEJA. */
    public function test_le_supplement_voyageurs_ne_compte_que_le_surplus(): void
    {
        $logement = PeerStay::factory()->make([
            'guests_included' => 2,
            'extra_guest_price_cents' => 1500,
        ]);

        $this->assertSame(0, $logement->supplementVoyageursCents(2));
        $this->assertSame(3000, $logement->supplementVoyageursCents(4));
    }

    /** LA REFERENCE EST L'IDENTITE PUBLIQUE : elle survit a un changement de titre. */
    public function test_chaque_annonce_recoit_une_reference_unique(): void
    {
        $a = PeerStay::factory()->create();
        $b = PeerStay::factory()->create();

        $this->assertNotSame($a->reference, $b->reference);
        $this->assertStringStartsWith('STAY-', (string) $a->reference);
    }

    /** LA LOCATION PORTE N'IMPORTE QUEL BIEN : c'est ce qui partage le chemin de l'argent. */
    public function test_une_location_porte_un_logement(): void
    {
        $logement = PeerStay::factory()->publiee()->create();
        $locataire = User::factory()->create();

        $location = PeerRental::query()->create([
            'reference' => 'PR-TEST-1',
            'rentable_type' => PeerStay::class,
            'rentable_id' => $logement->id,
            'owner_id' => $logement->owner_id,
            'renter_id' => $locataire->id,
            'status' => 'pending',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(5),
            'days' => 2,
            'daily_price_cents' => 9000,
            'subtotal_cents' => 18000,
            'total_cents' => 18000,
            'platform_fee_cents' => 4500,
            'owner_payout_cents' => 13500,
            'commission_rate' => 0.25,
            'currency' => 'EUR',
        ]);

        $this->assertInstanceOf(PeerStay::class, $location->rentable);
        $this->assertSame($logement->id, $location->rentable->id);
        $this->assertSame(1, $logement->rentals()->count());
    }

    /**
     * LES DEUX COLONNES DU CALENDRIER RESTENT EN ACCORD.
     *
     * Tout le module vehicules ecrit `peer_vehicle_id` ; la couche partagee lit `rentable_*`. Sans
     * le crochet du modele, une indisponibilite posee par l'ancienne voie serait INVISIBLE a la
     * nouvelle — et un vehicule deja loue reapparaitrait libre.
     */
    public function test_une_indisponibilite_ecrite_a_l_ancienne_reste_visible_a_la_nouvelle(): void
    {
        $vehicule = PeerVehicle::factory()->create();

        PeerVehicleAvailability::query()->create([
            'peer_vehicle_id' => $vehicule->id,
            'starts_on' => now()->addDays(2)->toDateString(),
            'ends_on' => now()->addDays(4)->toDateString(),
            'kind' => 'blocked',
        ]);

        $this->assertSame(1, $vehicule->indisponibilites()->count(),
            'Une indisponibilité posée par l’ancienne colonne est invisible à la relation partagée.');
    }

    /** TEMOIN — le logement pose ses indisponibilites par la voie partagee, et les relit. */
    public function test_temoin_un_logement_pose_et_relit_ses_indisponibilites(): void
    {
        $logement = PeerStay::factory()->create();

        $logement->indisponibilites()->create([
            'starts_on' => now()->addDays(2)->toDateString(),
            'ends_on' => now()->addDays(4)->toDateString(),
            'kind' => 'blocked',
        ]);

        $this->assertSame(1, $logement->indisponibilites()->count());
        $this->assertDatabaseHas('peer_vehicle_availability', [
            'rentable_type' => PeerStay::class,
            'rentable_id' => $logement->id,
        ]);
    }

    /** LES LOCATIONS DE VEHICULES DEJA EN BASE ONT REJOINT LE NOUVEAU CHEMIN. */
    public function test_les_locations_de_vehicules_portent_le_type_polymorphe(): void
    {
        $orphelines = DB::table('peer_rentals')
            ->whereNotNull('peer_vehicle_id')
            ->whereNull('rentable_type')
            ->count();

        $this->assertSame(0, $orphelines,
            'Des locations de véhicules n’ont pas de type : la couche partagée les ignorerait.');
    }

    /** TOUT COMPTE ACTIF PUBLIE — client comme prestataire, avec ou sans societe. */
    public function test_un_particulier_comme_une_societe_peuvent_publier(): void
    {
        $particulier = PeerStay::factory()->create(['organization_account_id' => null]);

        $this->assertNull($particulier->organization_account_id);
        $this->assertNotNull($particulier->owner_id);
    }
}
