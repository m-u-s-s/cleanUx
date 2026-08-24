<?php

namespace Tests\Feature\Employe;

use App\Livewire\EmployeDashboard;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le prestataire voit les contraintes du terrain AVANT d'intervenir.
 *
 * `materiel_fournit` et `photos_reference` ne s'affichaient que dans `x-rdv-cleaning-card`, un
 * composant qu'aucune page ne montait : le prestataire ne savait ni s'il devait apporter son
 * materiel, ni que le client avait joint des photos. Et la surface lisait `surface`, une colonne
 * qui n'existe pas — l'API renseigne `surface_m2`, l'ecran affichait un tiret.
 */
class ContraintesTerrainVisiblesTest extends TestCase
{
    use RefreshDatabase;

    private function employeAvecRendezVous(array $attributs = []): User
    {
        $employe = User::factory()->employe()->create();

        Booking::factory()->create(array_merge([
            'client_id' => User::factory()->client()->create()->id,
            'employe_id' => $employe->id,
            'date' => today()->toDateString(),
            'heure' => '10:00:00',
            'status' => 'confirme',
        ], $attributs));

        $this->actingAs($employe);

        return $employe;
    }

    public function test_le_materiel_fourni_est_affiche(): void
    {
        $this->employeAvecRendezVous(['materiel_fournit' => true]);

        Livewire::test(EmployeDashboard::class)->assertSee('Matériel fourni');
    }

    /**
     * DEUX NOTIONS DISTINCTES, PAS UN DOUBLON.
     *
     * Le web fait choisir une TRANCHE (`moins_50`, `50_100`...) : `CreateBookingAction` l'ecrit et
     * `BookingEstimatorService` en tire le prix ET la duree. L'API, elle, recoit une MESURE exacte
     * en `surface_m2`. La tranche prime — c'est elle qui a servi a facturer.
     */
    public function test_la_tranche_choisie_sur_le_web_prime(): void
    {
        $this->employeAvecRendezVous(['surface' => '50_100', 'surface_m2' => 85]);

        Livewire::test(EmployeDashboard::class)
            ->assertSee('50_100')
            ->assertDontSee('85 m²');
    }

    /** Une reservation venue de l'API n'a pas de tranche : sa mesure vaut mieux qu'un tiret. */
    public function test_sans_tranche_la_mesure_de_l_api_s_affiche(): void
    {
        $this->employeAvecRendezVous(['surface' => null, 'surface_m2' => 85]);

        Livewire::test(EmployeDashboard::class)->assertSee('85 m²');
    }

    /** TEMOIN — sans lui, un gabarit qui ecrirait « m² » en dur passerait pour correct. */
    public function test_sans_tranche_ni_mesure_rien_n_est_invente(): void
    {
        $this->employeAvecRendezVous(['surface' => null, 'surface_m2' => null]);

        Livewire::test(EmployeDashboard::class)->assertDontSee('m²');
    }

    public function test_les_photos_jointes_par_le_client_sont_montrees(): void
    {
        $this->employeAvecRendezVous(['photos_reference' => ['rendezvous/photos/salon.jpg']]);

        Livewire::test(EmployeDashboard::class)->assertSee('Photos de référence');
    }

    /** TEMOIN — le bloc ne s'affiche pas quand le client n'a joint aucune photo. */
    public function test_sans_photo_le_bloc_ne_s_affiche_pas(): void
    {
        $this->employeAvecRendezVous(['photos_reference' => []]);

        Livewire::test(EmployeDashboard::class)->assertDontSee('Photos de référence');
    }
}
