<?php

namespace Tests\Feature\Rental;

use App\Livewire\Rental\LocationCatalogue;
use App\Livewire\Rental\LocationConfirmation;
use App\Livewire\Rental\LocationEntryTile;
use App\Livewire\Rental\LocationVehicle;
use App\Models\RentalBooking;
use App\Models\RentalPickupPoint;
use App\Models\RentalVehicle;
use App\Models\RentalVehicleMedia;
use App\Services\Rental\RentalAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE PARCOURS DE LOCATION, DE LA CASE DU CATALOGUE À LA CONFIRMATION.
 *
 * Il ne partage aucun composant avec le parcours de commande : là on va du secteur au métier puis
 * aux questions pour trouver un professionnel, ici l'objet est visible dès la première seconde et
 * c'est le client qui se déplace. Ce fichier vérifie le chemin entier ET l'isolation.
 */
class ParcoursDeLocationTest extends TestCase
{
    use RefreshDatabase;

    private function vehiculeEnVitrine(array $attributs = []): RentalVehicle
    {
        return RentalVehicle::factory()->actif()->create($attributs + [
            'pickup_point_id' => RentalPickupPoint::factory()->create([
                'name' => 'Agence Centre',
                'address' => 'Rue du Parc 12',
                'postal_code' => '1000',
                'city' => 'Bruxelles',
            ])->id,
        ]);
    }

    /**
     * Une réservation en brouillon, ACCESSIBLE au visiteur du test.
     *
     * `LocationConfirmation` refuse une référence qui n'appartient ni au compte connecté ni au
     * jeton de session : la référence est aléatoire, mais un lien se partage, et sans ce contrôle
     * elle ouvrirait le nom, le téléphone et le permis d'un tiers. Les tests doivent donc porter le
     * jeton, comme un vrai visiteur.
     *
     * @param  array<string, mixed>  $attributs
     */
    private function brouillonAMoi(RentalVehicle $vehicule, array $attributs = []): RentalBooking
    {
        $jeton = 'jeton-de-test';
        session()->put('rental_session_token', $jeton);

        return RentalBooking::factory()->brouillon()->create($attributs + [
            'rental_vehicle_id' => $vehicule->id,
            'session_token' => $jeton,
        ]);
    }

    // ── La case du catalogue ─────────────────────────────────────────────

    /**
     * SANS VOITURE, LA CASE N'EXISTE PAS.
     *
     * C'est la demande, et c'est aussi ce que fait déjà le carrousel des secteurs pour les métiers
     * non servables : une porte qui promet du choix devant une vitrine vide apprend au client que
     * la plateforme annonce ce qu'elle ne sait pas faire.
     */
    public function test_la_case_location_est_absente_sans_vehicule(): void
    {
        Livewire::test(LocationEntryTile::class)
            ->assertDontSee('Location de voitures');
    }

    /** TÉMOIN — avec une voiture en vitrine, la case apparaît. */
    public function test_temoin_la_case_apparait_avec_un_vehicule(): void
    {
        $this->vehiculeEnVitrine();

        Livewire::test(LocationEntryTile::class)
            ->assertSee('Location de voitures')
            ->assertSee('1 véhicule disponible');
    }

    /**
     * UNE VOITURE FERMÉE NE FAIT PAS APPARAÎTRE LA CASE.
     *
     * Sans ce test, l'entrée s'ouvrirait sur un catalogue vide dès qu'un administrateur commence à
     * saisir une fiche — et une fiche naît fermée, exprès.
     */
    public function test_un_vehicule_ferme_ne_fait_pas_apparaitre_la_case(): void
    {
        RentalVehicle::factory()->create(['is_active' => false]);

        Livewire::test(LocationEntryTile::class)->assertDontSee('Location de voitures');
    }

    /**
     * UNE VOITURE ENTIÈREMENT LOUÉE NE FAIT PAS APPARAÎTRE LA CASE NON PLUS.
     *
     * C'est le lien entre les deux exigences : « ne pas montrer les voitures louées » et « masquer
     * l'entrée s'il n'y a rien » sont le même calcul. Si l'un des deux passait par une autre
     * requête, la case promettrait une voiture que la vitrine ne montrerait pas.
     */
    public function test_une_voiture_louee_maintenant_ne_fait_pas_apparaitre_la_case(): void
    {
        $vehicule = $this->vehiculeEnVitrine();

        RentalBooking::factory()->create([
            'rental_vehicle_id' => $vehicule->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(5),
            'status' => RentalBooking::STATUT_CONFIRMEE,
        ]);

        Livewire::test(LocationEntryTile::class)->assertDontSee('Location de voitures');
    }

    // ── La vitrine ───────────────────────────────────────────────────────

    public function test_le_catalogue_montre_les_voitures_en_vitrine(): void
    {
        $vehicule = $this->vehiculeEnVitrine(['brand' => 'Renault', 'model' => 'Clio']);

        $this->get(route('location.catalogue'))
            ->assertOk()
            ->assertSee('Renault Clio');

        $this->assertTrue($vehicule->is_active);
    }

    /** Les filtres n'affichent que des valeurs qui ont des voitures derrière elles. */
    public function test_les_filtres_ne_proposent_que_ce_qui_existe(): void
    {
        $this->vehiculeEnVitrine(['category' => 'suv', 'transmission' => 'automatique']);

        Livewire::test(LocationCatalogue::class)
            ->assertSee('Suv')
            ->assertDontSee('Monospace');
    }

    /** Un filtre qui ne rend rien s'explique au lieu d'afficher une page vide. */
    public function test_une_vitrine_vide_explique_pourquoi(): void
    {
        $this->vehiculeEnVitrine(['category' => 'citadine']);

        Livewire::test(LocationCatalogue::class)
            ->set('categorie', 'utilitaire')
            ->assertSee('Aucun véhicule sur ces critères');
    }

    // ── La fiche et le formulaire ────────────────────────────────────────

    /**
     * UNE VOITURE HORS VITRINE N'A PAS DE FICHE PUBLIQUE.
     *
     * `is_active` décide seul de la présence au catalogue ; laisser son URL ouverte permettrait de
     * réserver un véhicule que l'administrateur vient justement de retirer — en tapant l'adresse,
     * ou depuis un lien partagé la veille.
     */
    public function test_la_fiche_dune_voiture_fermee_repond_404(): void
    {
        $vehicule = RentalVehicle::factory()->create(['is_active' => false]);

        $this->get(route('location.vehicule', ['vehicle' => $vehicule->id]))->assertNotFound();
    }

    public function test_la_fiche_montre_les_deux_prix(): void
    {
        $vehicule = $this->vehiculeEnVitrine([
            'daily_price_cents' => 5000,
            'waiver_daily_price_cents' => 1500,
            'deposit_cents' => 80000,
            'waiver_deposit_cents' => 15000,
        ]);

        Livewire::test(LocationVehicle::class, ['vehicle' => $vehicule])
            ->assertSee('Sans garantie')
            ->assertSee('Avec garantie');
    }

    /**
     * LE PARCOURS COMPLET : formulaire, récapitulatif, confirmation.
     *
     * C'est le seul test qui prouve que les trois écrans se parlent. Chacun pris isolément peut
     * être vert pendant que le chemin, lui, est rompu — ce dépôt en a plusieurs exemples.
     */
    public function test_un_client_reserve_de_bout_en_bout(): void
    {
        $vehicule = $this->vehiculeEnVitrine(['daily_price_cents' => 5000, 'min_rental_days' => 1]);

        Livewire::test(LocationVehicle::class, ['vehicle' => $vehicule])
            ->set('debut', now()->addDays(2)->setTime(9, 0)->format('Y-m-d\TH:i'))
            ->set('fin', now()->addDays(5)->setTime(9, 0)->format('Y-m-d\TH:i'))
            ->set('driverFirstName', 'Alice')
            ->set('driverLastName', 'Dubois')
            ->set('driverBirthdate', now()->subYears(35)->toDateString())
            ->set('driverEmail', 'alice@example.test')
            ->set('licenseNumber', 'BE1234567')
            ->set('licenseCountry', 'BE')
            ->set('licenseIssuedAt', now()->subYears(10)->toDateString())
            ->call('reserver')
            ->assertHasNoErrors();

        $location = RentalBooking::query()->latest('id')->firstOrFail();

        $this->assertSame(RentalBooking::STATUT_BROUILLON, $location->status);
        $this->assertSame(15000, $location->total_cents);
        // L'ADRESSE EST COPIÉE au moment de préparer : l'agence peut déménager ensuite.
        $this->assertStringContainsString('Rue du Parc 12', (string) $location->pickup_address);

        Livewire::test(LocationConfirmation::class, ['reference' => $location->reference])
            ->call('confirmer')
            ->assertHasNoErrors();

        $this->assertSame(RentalBooking::STATUT_CONFIRMEE, $location->refresh()->status);

        // ET LA VOITURE DISPARAÎT DE LA VITRINE sur cette période : c'est le bouclage de la règle.
        $this->assertFalse(
            app(RentalAvailability::class)->estLibre(
                $vehicule,
                $location->starts_at,
                $location->ends_at,
            ),
        );
    }

    /**
     * UN CONDUCTEUR TROP JEUNE EST REFUSÉ — au jour du DÉPART, pas au jour de la réservation.
     *
     * Un client de vingt ans qui réserve pour dans six mois aura l'âge au volant, et le refuser
     * aujourd'hui serait faux. Ici il ne l'aura pas : le refus est juste.
     */
    public function test_un_conducteur_trop_jeune_est_refuse(): void
    {
        $vehicule = $this->vehiculeEnVitrine(['min_driver_age' => 25]);

        $location = $this->brouillonAMoi($vehicule, [
            'driver_birthdate' => now()->subYears(20)->toDateString(),
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(5),
        ]);

        Livewire::test(LocationConfirmation::class, ['reference' => $location->reference])
            ->call('confirmer');

        $this->assertSame(RentalBooking::STATUT_BROUILLON, $location->refresh()->status);
    }

    /** TÉMOIN — un conducteur qui remplit les conditions passe. */
    public function test_temoin_un_conducteur_eligible_passe(): void
    {
        $vehicule = $this->vehiculeEnVitrine(['min_driver_age' => 25, 'min_license_years' => 2]);

        $location = $this->brouillonAMoi($vehicule, [
            'driver_birthdate' => now()->subYears(40)->toDateString(),
            'license_issued_at' => now()->subYears(15)->toDateString(),
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(5),
        ]);

        Livewire::test(LocationConfirmation::class, ['reference' => $location->reference])
            ->call('confirmer');

        $this->assertSame(RentalBooking::STATUT_CONFIRMEE, $location->refresh()->status);
    }

    /**
     * DEUX CLIENTS NE PEUVENT PAS PRENDRE LA MÊME VOITURE.
     *
     * Entre l'affichage et le clic, quelqu'un d'autre a pu réserver. C'est le seul défaut de ce
     * module qui se découvrirait devant le client, au comptoir.
     */
    public function test_la_voiture_prise_entre_temps_est_refusee(): void
    {
        $vehicule = $this->vehiculeEnVitrine();

        $mien = $this->brouillonAMoi($vehicule, [
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(6),
        ]);

        // Quelqu'un d'autre confirme pendant que le premier hésite.
        RentalBooking::factory()->create([
            'rental_vehicle_id' => $vehicule->id,
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(5),
            'status' => RentalBooking::STATUT_CONFIRMEE,
        ]);

        Livewire::test(LocationConfirmation::class, ['reference' => $mien->reference])
            ->call('confirmer')
            ->assertSee('vient d’être réservé');

        $this->assertSame(RentalBooking::STATUT_BROUILLON, $mien->refresh()->status);
    }

    /**
     * LA RÉFÉRENCE SEULE N'OUVRE PAS LE RÉCAPITULATIF D'UN AUTRE.
     *
     * Elle est aléatoire, mais un lien se partage. Sans ce contrôle, une référence transmise
     * ouvrirait le nom, le téléphone et le numéro de permis d'un tiers.
     */
    public function test_le_recapitulatif_dun_autre_est_refuse(): void
    {
        $vehicule = $this->vehiculeEnVitrine();

        $location = RentalBooking::factory()->brouillon()->create([
            'rental_vehicle_id' => $vehicule->id,
            'session_token' => 'le-jeton-de-quelquun-dautre',
        ]);

        $this->get(route('location.recapitulatif', ['reference' => $location->reference]))
            ->assertForbidden();
    }

    // ── Le 360°, choisi véhicule par véhicule ────────────────────────────

    public function test_la_rotation_photo_saffiche_quand_elle_existe(): void
    {
        $vehicule = $this->vehiculeEnVitrine();

        foreach (range(0, 5) as $position) {
            RentalVehicleMedia::factory()->rotation($position)->create(['rental_vehicle_id' => $vehicule->id]);
        }

        Livewire::test(LocationVehicle::class, ['vehicle' => $vehicule])
            ->assertSee('Faites glisser pour tourner');
    }

    /**
     * LE MODÈLE 3D PREND LE PAS SUR LA ROTATION PHOTO.
     *
     * Un véhicule peut porter les deux ; afficher les deux donnerait deux vues concurrentes de la
     * même voiture sur le même écran. Le fichier 3D est le plus riche, il gagne.
     */
    public function test_le_modele_3d_prend_le_pas_sur_la_rotation(): void
    {
        $vehicule = $this->vehiculeEnVitrine();

        RentalVehicleMedia::factory()->rotation(0)->create(['rental_vehicle_id' => $vehicule->id]);
        RentalVehicleMedia::factory()->modele3d()->create(['rental_vehicle_id' => $vehicule->id]);

        /*
         * ON DISTINGUE SUR UN TEXTE PROPRE À CHAQUE VUE.
         *
         * Première version : « Faites glisser pour tourner » absent quand la 3D gagne. Elle était
         * fausse — le visualiseur 3D affiche exactement la même invite, suivie de « molette pour
         * zoomer ». Le test échouait donc alors que le code faisait ce qu'il fallait.
         *
         * On vise donc l'étiquette d'accessibilité, qui nomme sans ambiguïté la vue montée.
         */
        Livewire::test(LocationVehicle::class, ['vehicle' => $vehicule])
            ->assertSee('modele3dLocation', false)
            ->assertSee('Modèle 3D du véhicule')
            ->assertDontSee('Vue à 360 degrés du véhicule');
    }

    /** Sans média 360°, la fiche reste utilisable : elle montre la photo, ou rien. */
    public function test_une_voiture_sans_360_saffiche_quand_meme(): void
    {
        $vehicule = $this->vehiculeEnVitrine(['brand' => 'Toyota', 'model' => 'Yaris']);

        Livewire::test(LocationVehicle::class, ['vehicle' => $vehicule])
            ->assertOk()
            ->assertSee('Toyota Yaris');
    }
}
