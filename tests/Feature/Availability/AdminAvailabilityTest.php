<?php

namespace Tests\Feature\Availability;

use App\Livewire\Admin\Availability\AvailabilityCenter;
use App\Livewire\Admin\Availability\ProviderAvailabilityDetail;
use App\Models\AvailabilityException;
use App\Models\AvailabilitySlot;
use App\Models\User;
use App\Services\Admin\AdminAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'ADMINISTRATION VOIT LES COMPTES MUETS, ET PEUT AGIR DESSUS.
 *
 * Le centre listait `whereHas('availabilitySlots')` : il ne montrait QUE les prestataires déjà
 * configurés. Le compte à zéro disponibilité — celui qu'une administration doit repérer, puisqu'il
 * est injoignable à la planification sans le savoir — était structurellement absent. Et aucun nom
 * n'était cliquable : voir un problème sans pouvoir le traiter.
 */
class AdminAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function prestataire(string $nom = 'Presta'): User
    {
        return User::factory()->employe()->create(['name' => $nom, 'is_active' => true]);
    }

    private function creneau(User $u, int $weekday = 1): AvailabilitySlot
    {
        return AvailabilitySlot::create([
            'provider_user_id' => $u->id,
            'weekday' => $weekday,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'timezone' => 'Europe/Brussels',
            'is_active' => true,
        ]);
    }

    // ─── Le centre ───────────────────────────────────────────────────────────────────────────

    public function test_le_centre_liste_aussi_les_prestataires_sans_creneau(): void
    {
        $muet = $this->prestataire('Sans Dispo');
        $configure = $this->prestataire('Avec Dispo');
        $this->creneau($configure);

        Livewire::actingAs($this->admin())
            ->test(AvailabilityCenter::class)
            ->assertSee('Sans Dispo')   // ← invisible avant
            ->assertSee('Avec Dispo');

        $this->assertNotNull($muet->fresh());
    }

    public function test_le_compteur_des_comptes_muets_est_expose(): void
    {
        $this->prestataire('Muet A');
        $this->prestataire('Muet B');
        $this->creneau($this->prestataire('Configure'));

        Livewire::actingAs($this->admin())
            ->test(AvailabilityCenter::class)
            ->assertViewHas('kpis', fn ($kpis) => $kpis['providers_without_slots'] === 2
                && $kpis['providers_total'] === 3);
    }

    public function test_le_filtre_isole_les_comptes_sans_disponibilite(): void
    {
        $this->prestataire('Muet');
        $this->creneau($this->prestataire('Configure'));

        Livewire::actingAs($this->admin())
            ->test(AvailabilityCenter::class)
            ->set('filtre', 'sans_creneau')
            ->assertSee('Muet')
            ->assertDontSee('Configure');
    }

    /** Le nom mène à la fiche : c'est le geste demandé. */
    public function test_le_nom_pointe_vers_la_fiche_du_prestataire(): void
    {
        $presta = $this->prestataire('Cliquable');

        Livewire::actingAs($this->admin())
            ->test(AvailabilityCenter::class)
            ->assertSee(route('admin.availability.provider', $presta), false);
    }

    // ─── La fiche ────────────────────────────────────────────────────────────────────────────

    public function test_l_admin_ajoute_un_creneau_pour_un_prestataire(): void
    {
        $presta = $this->prestataire();

        Livewire::actingAs($this->admin())
            ->test(ProviderAvailabilityDetail::class, ['user' => $presta])
            ->set('weekday', 3)
            ->set('heure_debut', '09:00')
            ->set('heure_fin', '12:00')
            ->call('save')
            ->assertHasNoErrors();

        $slot = AvailabilitySlot::where('provider_user_id', $presta->id)->sole();
        $this->assertSame(3, (int) $slot->weekday);
    }

    /**
     * La règle de chevauchement est celle du prestataire, à l'identique : c'est le même
     * `AvailabilityEditor`. Deux implémentations divergeraient.
     */
    public function test_le_chevauchement_est_refuse_cote_admin_aussi(): void
    {
        $presta = $this->prestataire();
        $this->creneau($presta, 1);

        Livewire::actingAs($this->admin())
            ->test(ProviderAvailabilityDetail::class, ['user' => $presta])
            ->set('weekday', 1)
            ->set('heure_debut', '10:00')
            ->set('heure_fin', '12:00')
            ->call('save')
            ->assertHasErrors('heure_debut');

        $this->assertSame(1, AvailabilitySlot::where('provider_user_id', $presta->id)->count());
    }

    public function test_fermer_un_jour_depuis_l_admin_laisse_la_semaine_intacte(): void
    {
        $presta = $this->prestataire();
        $this->creneau($presta, 2);

        $mardi = now()->startOfWeek()->addDay()->toDateString();

        Livewire::actingAs($this->admin())
            ->test(ProviderAvailabilityDetail::class, ['user' => $presta])
            ->call('closeDay', $mardi);

        $this->assertSame(1, AvailabilitySlot::where('provider_user_id', $presta->id)->count());
        $this->assertSame(1, AvailabilityException::where('provider_user_id', $presta->id)->count());
    }

    public function test_appliquer_la_semaine_par_defaut_depuis_la_fiche(): void
    {
        $presta = $this->prestataire();

        Livewire::actingAs($this->admin())
            ->test(ProviderAvailabilityDetail::class, ['user' => $presta])
            ->call('applyDefaultWeek');

        $this->assertSame(7, AvailabilitySlot::where('provider_user_id', $presta->id)->count());
    }

    /** Idempotent : un prestataire qui a déjà choisi n'est pas écrasé depuis l'admin non plus. */
    public function test_appliquer_le_defaut_ne_touche_pas_une_semaine_existante(): void
    {
        $presta = $this->prestataire();
        $this->creneau($presta, 4);

        Livewire::actingAs($this->admin())
            ->test(ProviderAvailabilityDetail::class, ['user' => $presta])
            ->call('applyDefaultWeek');

        $this->assertSame(1, AvailabilitySlot::where('provider_user_id', $presta->id)->count());
    }

    /**
     * La fiche d'un compte qui n'est pas prestataire n'existe pas : un écran de créneaux pour
     * quelqu'un qui n'en aura jamais laisserait croire que la configuration a été faite.
     */
    public function test_la_fiche_d_un_client_est_introuvable(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.availability.provider', $client))
            ->assertNotFound();
    }

    /** Témoin : la fiche d'un vrai prestataire, elle, s'ouvre. */
    public function test_la_fiche_d_un_prestataire_s_ouvre(): void
    {
        $presta = $this->prestataire('Ouvrable');

        $this->actingAs($this->admin())
            ->get(route('admin.availability.provider', $presta))
            ->assertOk()
            ->assertSee('Ouvrable');
    }

    public function test_un_non_admin_ne_peut_pas_ouvrir_la_fiche(): void
    {
        $presta = $this->prestataire();

        $this->actingAs($this->prestataire('Curieux'))
            ->get(route('admin.availability.provider', $presta))
            ->assertForbidden();
    }

    // ─── Les alertes ─────────────────────────────────────────────────────────────────────────

    /**
     * IL N'Y AVAIT AUCUNE ALERTE DE DISPONIBILITÉ.
     *
     * Les quatre existantes surveillent des missions déjà lancées. Un prestataire injoignable ne
     * produit aucune mission — donc aucun retard, donc aucun signal. Son silence passait pour du
     * calme.
     */
    public function test_l_alerte_signale_les_prestataires_sans_disponibilite(): void
    {
        $muet = $this->prestataire('Muet');
        $this->creneau($this->prestataire('Configure'));

        $alertes = app(AdminAlertService::class)->alerts();

        $this->assertArrayHasKey('providers_without_availability', $alertes);
        $this->assertCount(1, $alertes['providers_without_availability']);
        $this->assertSame($muet->id, $alertes['providers_without_availability']->first()->id);
    }

    public function test_l_alerte_signale_une_semaine_entierement_fermee(): void
    {
        $presta = $this->prestataire('Absent');
        $this->creneau($presta, 1);

        // Sept jours fermés d'affilée : configuré, mais absent.
        foreach (range(0, 6) as $offset) {
            AvailabilityException::create([
                'provider_user_id' => $presta->id,
                'date' => now()->addDays($offset)->toDateString(),
                'exception_type' => AvailabilityException::TYPE_CLOSED,
            ]);
        }

        $alertes = app(AdminAlertService::class)->alerts();

        $this->assertCount(1, $alertes['providers_fully_closed_week']);
        $this->assertSame($presta->id, $alertes['providers_fully_closed_week']->first()->id);
    }

    /** Témoin : six jours fermés ne déclenchent pas l'alerte. Sans lui, le seuil ne serait pas testé. */
    public function test_six_jours_fermes_ne_declenchent_pas_l_alerte(): void
    {
        $presta = $this->prestataire();
        $this->creneau($presta, 1);

        foreach (range(0, 5) as $offset) {
            AvailabilityException::create([
                'provider_user_id' => $presta->id,
                'date' => now()->addDays($offset)->toDateString(),
                'exception_type' => AvailabilityException::TYPE_CLOSED,
            ]);
        }

        $this->assertCount(0, app(AdminAlertService::class)->alerts()['providers_fully_closed_week']);
    }
}
