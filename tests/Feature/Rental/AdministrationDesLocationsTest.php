<?php

namespace Tests\Feature\Rental;

use App\Livewire\Admin\Rental\NosLocationsCenter;
use App\Models\RentalBooking;
use App\Models\RentalPickupPoint;
use App\Models\RentalVehicle;
use App\Models\User;
use App\Services\Rental\RentalAvailability;
use App\Support\Navigation\ModuleCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'ADMINISTRATEUR PILOTE TOUT LE COMPTOIR DEPUIS « NOS LOCATIONS ».
 *
 * Prix, garantie, mise en vitrine, agences, médias, réservations : tout est dans ce module et nulle
 * part ailleurs. Ce fichier vérifie que chaque levier existe ET qu'il agit — un écran de réglages
 * décoratif est la forme d'échec que ce dépôt collectionne.
 *
 * ── LA CAPACITÉ EST À PART DE FLEET, ET C'EST VOULU ──────────────────────────────────────────
 *
 * `manage-rentals` n'est pas `manage-orchestration` : on peut confier le comptoir de location sans
 * ouvrir la gestion du parc interne, et l'inverse. Ce sont deux métiers.
 */
class AdministrationDesLocationsTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<string>  $capacites */
    private function admin(array $capacites): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $admin->forceFill(['platform_role' => 'admin', 'permissions' => $capacites])->save();

        return $admin->refresh();
    }

    // ── La porte ─────────────────────────────────────────────────────────

    public function test_le_gestionnaire_de_location_voit_la_tuile(): void
    {
        $this->actingAs($this->admin(['manage-rentals']));

        $cles = collect(ModuleCatalogue::pourContexte('admin'))
            ->flatMap(fn (array $groupe) => $groupe['modules'])
            ->pluck('key');

        $this->assertContains('admin:admin.rentals.center', $cles);
    }

    /**
     * TÉMOIN INVERSE — un administrateur de flotte interne ne l'a pas.
     *
     * Sans lui, la capacité ne servirait à rien : donner la location sans donner le reste suppose
     * que le reste ne donne pas la location.
     */
    public function test_temoin_un_admin_de_flotte_ne_voit_pas_la_tuile(): void
    {
        $this->actingAs($this->admin(['manage-orchestration']));

        $cles = collect(ModuleCatalogue::pourContexte('admin'))
            ->flatMap(fn (array $groupe) => $groupe['modules'])
            ->pluck('key');

        $this->assertNotContains('admin:admin.rentals.center', $cles);
    }

    public function test_lecran_refuse_sans_la_capacite(): void
    {
        $this->actingAs($this->admin(['manage-orchestration']));

        $this->get(route('admin.rentals.center'))->assertForbidden();
    }

    public function test_lecran_souvre_avec_la_capacite(): void
    {
        $this->actingAs($this->admin(['manage-rentals']));

        $this->get(route('admin.rentals.center'))->assertSuccessful();
    }

    // ── Le parc et ses tarifs ────────────────────────────────────────────

    /**
     * UNE VOITURE CRÉÉE ARRIVE FERMÉE.
     *
     * Même prudence que pour un pays neuf du catalogue géographique : une faute de frappe sur un
     * tarif ne doit pas rendre un véhicule louable dans la seconde. La mise en vitrine est un geste
     * distinct, et il se voit.
     */
    public function test_un_vehicule_cree_nest_pas_en_vitrine(): void
    {
        $this->actingAs($this->admin(['manage-rentals']));

        Livewire::test(NosLocationsCenter::class)
            ->set('fiche.brand', 'Renault')
            ->set('fiche.model', 'Clio')
            ->set('fiche.daily_price', '45')
            ->call('enregistrerLeVehicule')
            ->assertHasNoErrors();

        $vehicule = RentalVehicle::query()->firstOrFail();

        $this->assertFalse($vehicule->is_active, 'Une voiture neuve est entrée en vitrine sans qu’on le demande.');
        $this->assertSame(4500, $vehicule->daily_price_cents);
    }

    /** Et l'interrupteur la met en vitrine — c'est le geste séparé. */
    public function test_ladministrateur_met_une_voiture_en_vitrine(): void
    {
        $this->actingAs($this->admin(['manage-rentals']));
        $vehicule = RentalVehicle::factory()->create(['is_active' => false]);

        Livewire::test(NosLocationsCenter::class)->call('basculerLActivation', $vehicule->id);

        $this->assertTrue($vehicule->refresh()->is_active);
    }

    /**
     * LE PRIX ET LA GARANTIE SE SAISISSENT EN UNITÉS ET VIVENT EN CENTIMES.
     *
     * L'administrateur tape « 45,00 » ; la base garde 4500. Un `decimal` sur des prix journaliers
     * multipliés par un nombre de jours ramènerait des arrondis que personne ne vérifie.
     */
    public function test_les_montants_sont_convertis_en_centimes(): void
    {
        $this->actingAs($this->admin(['manage-rentals']));

        Livewire::test(NosLocationsCenter::class)
            ->set('fiche.brand', 'BMW')
            ->set('fiche.model', 'Serie 1')
            ->set('fiche.daily_price', '89.90')
            ->set('fiche.deposit', '1200')
            ->set('fiche.waiver_daily_price', '15.50')
            ->set('fiche.waiver_deposit', '250')
            ->call('enregistrerLeVehicule')
            ->assertHasNoErrors();

        $vehicule = RentalVehicle::query()->firstOrFail();

        $this->assertSame(8990, $vehicule->daily_price_cents);
        $this->assertSame(120000, $vehicule->deposit_cents);
        $this->assertSame(1550, $vehicule->waiver_daily_price_cents);
        $this->assertSame(25000, $vehicule->waiver_deposit_cents);
        $this->assertTrue($vehicule->proposeUneGarantie());
    }

    /** Une durée maximale plus courte que la minimale est refusée. */
    public function test_une_duree_incoherente_est_refusee(): void
    {
        $this->actingAs($this->admin(['manage-rentals']));

        Livewire::test(NosLocationsCenter::class)
            ->set('fiche.brand', 'Peugeot')
            ->set('fiche.model', '208')
            ->set('fiche.daily_price', '40')
            ->set('fiche.min_rental_days', 5)
            ->set('fiche.max_rental_days', 2)
            ->call('enregistrerLeVehicule')
            ->assertHasErrors('fiche.max_rental_days');
    }

    /**
     * UNE VOITURE EN COURS DE LOCATION NE SE RETIRE PAS DU PARC.
     *
     * Un client a la clé ; effacer la fiche laisserait une réservation vivante pointant vers un
     * véhicule disparu, et le comptoir n'aurait plus de quoi établir l'état des lieux au retour.
     */
    public function test_une_voiture_en_location_ne_se_retire_pas(): void
    {
        $this->actingAs($this->admin(['manage-rentals']));

        $vehicule = RentalVehicle::factory()->actif()->create();
        RentalBooking::factory()->create(['rental_vehicle_id' => $vehicule->id]);

        Livewire::test(NosLocationsCenter::class)->call('supprimerLeVehicule', $vehicule->id);

        $this->assertNotNull($vehicule->fresh(), 'Le véhicule a été retiré alors qu’il est loué.');
    }

    /**
     * TÉMOIN — une voiture libre se retire bien, SANS effacer son histoire.
     *
     * Le retrait est une suppression DOUCE : les locations passées gardent leur véhicule, y compris
     * devant un litige. Une voiture qui a servi ne s'efface pas de la comptabilité parce qu'on l'a
     * revendue.
     *
     * D'où `assertSoftDeleted` et non `assertNull($vehicule->fresh())` : `fresh()` requête SANS les
     * portées globales et retrouve donc la ligne supprimée. Ma première version échouait sur ce
     * détail en semblant dire que la suppression n'avait pas eu lieu — alors qu'elle avait
     * exactement l'effet voulu.
     */
    public function test_temoin_une_voiture_libre_se_retire(): void
    {
        $this->actingAs($this->admin(['manage-rentals']));
        $vehicule = RentalVehicle::factory()->create();

        Livewire::test(NosLocationsCenter::class)->call('supprimerLeVehicule', $vehicule->id);

        $this->assertNull(RentalVehicle::query()->find($vehicule->id), 'Le véhicule reste au catalogue.');
        $this->assertSoftDeleted($vehicule, [], 'rental_vehicles');
    }

    // ── Les agences ──────────────────────────────────────────────────────

    public function test_ladministrateur_cree_une_agence_de_retrait(): void
    {
        $this->actingAs($this->admin(['manage-rentals']));

        Livewire::test(NosLocationsCenter::class)
            ->set('tab', 'agences')
            ->set('agence.name', 'Agence Gare du Midi')
            ->set('agence.address', 'Avenue Fonsny 1')
            ->set('agence.city', 'Bruxelles')
            ->set('agence.country_code', 'be')
            ->call('enregistrerLAgence')
            ->assertHasNoErrors();

        $point = RentalPickupPoint::query()->firstOrFail();

        $this->assertSame('Agence Gare du Midi', $point->name);
        $this->assertSame('BE', $point->country_code, 'Le code pays doit être normalisé en majuscules.');
        $this->assertStringContainsString('Avenue Fonsny 1', $point->adresseComplete());
    }

    /**
     * LA DEVISE D'UN VÉHICULE SUIT LE PAYS DE SON AGENCE.
     *
     * Une agence marocaine loue en dirhams. C'est la même autorité que le reste de la plateforme —
     * jamais un littéral, jamais la devise de base par défaut.
     */
    public function test_la_devise_suit_le_pays_de_lagence(): void
    {
        $this->actingAs($this->admin(['manage-rentals']));

        $agence = RentalPickupPoint::factory()->create(['country_code' => 'MA']);

        Livewire::test(NosLocationsCenter::class)
            ->set('fiche.brand', 'Dacia')
            ->set('fiche.model', 'Logan')
            ->set('fiche.daily_price', '300')
            ->set('fiche.pickup_point_id', $agence->id)
            ->call('enregistrerLeVehicule')
            ->assertHasNoErrors();

        $this->assertSame('MAD', RentalVehicle::query()->firstOrFail()->currency);
    }

    // ── Le cycle d'une location ──────────────────────────────────────────

    /**
     * LE RETOUR REMET LA VOITURE AU CATALOGUE.
     *
     * Tant qu'elle est « retirée », elle reste bloquée. Ne pas enregistrer le retour la laisserait
     * invisible indéfiniment, et personne ne comprendrait pourquoi le parc rétrécit.
     */
    public function test_le_cycle_retrait_retour_libere_la_voiture(): void
    {
        $this->actingAs($this->admin(['manage-rentals']));

        $vehicule = RentalVehicle::factory()->actif()->create();
        $location = RentalBooking::factory()->create(['rental_vehicle_id' => $vehicule->id]);

        $composant = Livewire::test(NosLocationsCenter::class)->set('tab', 'locations');

        $composant->call('marquerRetiree', $location->id);
        $this->assertSame(RentalBooking::STATUT_RETIREE, $location->refresh()->status);
        $this->assertNotNull($location->picked_up_at);

        $composant->call('marquerRendue', $location->id);
        $this->assertSame(RentalBooking::STATUT_RENDUE, $location->refresh()->status);

        $this->assertTrue(
            app(RentalAvailability::class)->estLibre(
                $vehicule,
                $location->starts_at,
                $location->ends_at,
            ),
            'Le véhicule reste bloqué après son retour : le parc rétrécirait à chaque location.',
        );
    }
}
