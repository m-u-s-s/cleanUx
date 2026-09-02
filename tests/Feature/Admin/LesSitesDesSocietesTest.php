<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\OrganizationSitesManager;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'ADMINISTRATEUR GERE LES SITES DE TOUTES LES SOCIETES.
 *
 * L'ecran filtrait sur `Auth::user()->organization_account_id` — l'organisation de
 * L'ADMINISTRATEUR, qui n'en a aucune. La liste etait donc vide en permanence, et le bouton
 * d'ajout ecrivait un identifiant nul dans une colonne NOT NULL.
 */
class LesSitesDesSocietesTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_liste_montre_les_sites_de_toutes_les_societes(): void
    {
        $a = $this->siteDe('Alpha SA', 'Siege Alpha');
        $b = $this->siteDe('Beta SPRL', 'Depot Beta');

        Livewire::actingAs($this->admin())->test(OrganizationSitesManager::class)
            ->assertSee($a->name)
            ->assertSee($b->name)
            ->assertSee('Alpha SA')
            ->assertSee('Beta SPRL');
    }

    /**
     * TEMOIN — l'ancienne regle rendait bien ZERO.
     *
     * Sans lui, le test ci-dessus prouverait seulement que deux lignes s'affichent, pas que le
     * defaut a disparu : il passerait au vert sur un ecran qui n'a jamais eu le probleme.
     */
    public function test_temoin_l_ancienne_regle_ne_rendait_aucun_site(): void
    {
        $this->siteDe('Alpha SA', 'Siege Alpha');
        $this->siteDe('Beta SPRL', 'Depot Beta');

        $admin = $this->admin();
        $this->assertNull($admin->organization_account_id,
            'Un administrateur porte une organisation : le temoin ne mesure plus le bon defaut.');

        $ancienneRegle = OrganizationSite::query()
            ->where('organization_account_id', $admin->organization_account_id)
            ->count();

        $this->assertSame(0, $ancienneRegle, 'L ancienne regle rendait des lignes : le defaut n etait pas la.');
        $this->assertSame(2, OrganizationSite::query()->count(), 'Les sites existent pourtant bien.');
    }

    public function test_l_administrateur_cree_un_site_pour_une_societe_choisie(): void
    {
        $societe = OrganizationAccount::factory()->create(['name' => 'Gamma SA']);
        $zone = ServiceZone::factory()->create();

        Livewire::actingAs($this->admin())->test(OrganizationSitesManager::class)
            ->call('ouvrirCreation')
            ->set('organisation', (string) $societe->id)
            ->set('nom', 'Atelier Gamma')
            ->set('adresse', 'Rue du Test 7')
            ->set('codePostal', '1000')
            ->set('ville', 'Bruxelles')
            ->set('paysDuSite', 'BE')
            ->set('zoneDeService', (string) $zone->id)
            ->set('badge', true)
            ->set('frequence', 'weekly')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSet('formulaireOuvert', false);

        $this->assertDatabaseHas('organization_sites', [
            'organization_account_id' => $societe->id,
            'name' => 'Atelier Gamma',
            'service_zone_id' => $zone->id,
            'badge_required' => true,
            'cleaning_frequency' => 'weekly',
        ]);
    }

    /** LA SOCIETE EST OBLIGATOIRE : un site sans societe n'apparait sur aucun ecran. */
    public function test_un_site_sans_societe_est_refuse(): void
    {
        Livewire::actingAs($this->admin())->test(OrganizationSitesManager::class)
            ->call('ouvrirCreation')
            ->set('organisation', '')
            ->set('nom', 'Site orphelin')
            ->set('adresse', 'Rue du Test 7')
            ->set('codePostal', '1000')
            ->set('ville', 'Bruxelles')
            ->call('enregistrer')
            ->assertHasErrors(['organisation' => 'required']);

        $this->assertDatabaseMissing('organization_sites', ['name' => 'Site orphelin']);
    }

    public function test_l_administrateur_modifie_un_site_existant(): void
    {
        $site = $this->siteDe('Delta SA', 'Bureau Delta');

        Livewire::actingAs($this->admin())->test(OrganizationSitesManager::class)
            ->call('ouvrirEdition', $site->id)
            ->assertSet('nom', 'Bureau Delta')
            ->set('nom', 'Bureau Delta renove')
            ->set('alarme', true)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame('Bureau Delta renove', $site->fresh()->name);
        $this->assertTrue((bool) $site->fresh()->alarm_code_required);
    }

    /** ARCHIVER, PAS DETRUIRE : les reservations pointent vers le site. */
    public function test_l_archivage_conserve_la_ligne(): void
    {
        $site = $this->siteDe('Epsilon SA', 'Hall Epsilon');

        Livewire::actingAs($this->admin())->test(OrganizationSitesManager::class)
            ->call('demanderLaSuppression', $site->id)
            ->call('archiver');

        $this->assertSame('archived', $site->fresh()->status);
        $this->assertDatabaseHas('organization_sites', ['id' => $site->id]);
    }

    public function test_la_recherche_et_le_filtre_de_societe_reduisent_la_liste(): void
    {
        $alpha = $this->siteDe('Alpha SA', 'Siege Alpha');
        $beta = $this->siteDe('Beta SPRL', 'Depot Beta');

        Livewire::actingAs($this->admin())->test(OrganizationSitesManager::class)
            ->set('recherche', 'Depot')
            ->assertSee('Depot Beta')
            ->assertDontSee('Siege Alpha')
            ->set('recherche', '')
            ->set('organisationId', (string) $alpha->organization_account_id)
            ->assertSee('Siege Alpha')
            ->assertDontSee('Depot Beta');

        $this->assertNotSame($alpha->organization_account_id, $beta->organization_account_id);
    }

    /**
     * LA CAPACITE GARDE AUSSI LE COMPOSANT.
     *
     * `module_gate` pose `manage-entreprises` sur la route, mais `/livewire/update` ne rejoue
     * aucun middleware : sans garde dans le composant, tout administrateur y accedait.
     */
    public function test_un_administrateur_sans_la_capacite_est_refuse(): void
    {
        $sansCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-calendar'],
        ]);

        Livewire::actingAs($sansCapacite)->test(OrganizationSitesManager::class)
            ->assertForbidden();
    }

    /** TEMOIN — la meme visite avec la capacite aboutit ; sans lui le refus mesurerait une panne. */
    public function test_temoin_un_administrateur_avec_la_capacite_entre(): void
    {
        $avecCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-entreprises'],
        ]);

        Livewire::actingAs($avecCapacite)->test(OrganizationSitesManager::class)
            ->assertOk();
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }

    private function siteDe(string $societe, string $nomDuSite): OrganizationSite
    {
        return OrganizationSite::factory()->create([
            'organization_account_id' => OrganizationAccount::factory()->create(['name' => $societe])->id,
            'name' => $nomDuSite,
            'status' => 'active',
        ]);
    }
}
