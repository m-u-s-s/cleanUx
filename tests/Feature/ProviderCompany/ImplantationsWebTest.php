<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\Agencies;
use App\Livewire\ProviderCompany\DispatchCenter;
use App\Models\FieldTeam;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderAgency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** LES IMPLANTATIONS, CÔTÉ WEB — la moitié manquante d'un lot qu'on croyait terminé. */
class ImplantationsWebTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationAccount $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);
    }

    private function membre(OrganizationRole $role, ?OrganizationAccount $org = null): User
    {
        $org ??= $this->org;

        $user = User::factory()->employe()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->create([
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    // ─── La porte ────────────────────────────────────────────────────────────────────────────

    #[Test]
    public function la_route_web_des_implantations_repond(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $this->actingAs($patron)
            ->get(route('provider-company.agencies'))
            ->assertOk()
            ->assertSee('Nos implantations');
    }

    #[Test]
    public function l_implantation_figure_au_repertoire_des_modules(): void
    {
        $entrees = collect(config('modules.catalogue'))
            ->where('context', 'provider-company')
            ->pluck('route');

        // Un écran absent du répertoire est un écran que personne ne trouve : la route existe, et
        // aucun lien n'y mène. C'est exactement ce qui rend un module « livré » et invisible.
        $this->assertContains('provider-company.agencies', $entrees->all());
    }

    // ─── Créer, archiver, rouvrir ────────────────────────────────────────────────────────────

    #[Test]
    public function le_patron_declare_une_implantation(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        Livewire::actingAs($patron)
            ->test(Agencies::class)
            ->set('nom', 'Dépôt Bruxelles')
            ->set('ville', 'Bruxelles')
            ->call('creer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('provider_agencies', [
            'provider_organization_id' => $this->org->id,
            'name' => 'Dépôt Bruxelles',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function archiver_ne_supprime_pas_la_ligne(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $agence = ProviderAgency::create([
            'provider_organization_id' => $this->org->id,
            'name' => 'Dépôt hiver', 'slug' => 'depot-hiver', 'status' => 'active',
        ]);

        Livewire::actingAs($patron)
            ->test(Agencies::class)
            ->call('archiver', $agence->id)
            ->call('reactiver', $agence->id);

        // Rouvrir un dépôt fermé l'hiver ne doit pas demander de tout recréer : les équipes qui y
        // étaient rattachées et les missions passées le citent encore.
        $this->assertSame('active', $agence->fresh()->status);
    }

    /** VOIR N'EST PAS ÉCRIRE — et c'est le cas qui compte. */
    #[Test]
    public function le_repartiteur_voit_mais_ne_declare_pas(): void
    {
        $repartiteur = $this->membre(OrganizationRole::DISPATCHER);

        Livewire::actingAs($repartiteur)
            ->test(Agencies::class)
            ->set('nom', 'Dépôt sauvage')
            ->call('creer')
            ->assertForbidden();

        $this->assertDatabaseCount('provider_agencies', 0);
    }

    #[Test]
    public function le_nettoyeur_n_ouvre_meme_pas_l_ecran(): void
    {
        $nettoyeur = $this->membre(OrganizationRole::WORKER);

        // L'organisation de ses collègues n'est pas son affaire : il exécute des missions.
        $this->actingAs($nettoyeur)
            ->get(route('provider-company.agencies'))
            ->assertForbidden();
    }

    #[Test]
    public function une_implantation_d_une_autre_societe_est_hors_de_portee(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $concurrent = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value, 'status' => 'active',
        ]);
        $adverse = ProviderAgency::create([
            'provider_organization_id' => $concurrent->id,
            'name' => 'Dépôt du concurrent', 'slug' => 'depot-concurrent', 'status' => 'active',
        ]);

        Livewire::actingAs($patron)
            ->test(Agencies::class)
            ->call('archiver', $adverse->id)
            ->assertDontSee('Dépôt du concurrent');

        // Le scoping fait partie de la requête : un identifiant forgé ne touche rien plutôt que
        // d'échouer bruyamment, et ne révèle donc pas non plus l'existence de la cible.
        $this->assertSame('active', $adverse->fresh()->status);
    }

    // ─── Rattacher une équipe ────────────────────────────────────────────────────────────────

    #[Test]
    public function une_equipe_se_rattache_puis_se_detache(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $agence = ProviderAgency::create([
            'provider_organization_id' => $this->org->id,
            'name' => 'Dépôt Nord', 'slug' => 'depot-nord', 'status' => 'active',
        ]);
        $equipe = FieldTeam::factory()->create(['organization_account_id' => $this->org->id]);

        $composant = Livewire::actingAs($patron)->test(Agencies::class);

        $composant->call('rattacherEquipe', $agence->id, $equipe->id);
        $this->assertSame($agence->id, (int) $equipe->fresh()->provider_agency_id);

        // Détacher doit rester possible : une société réorganise, et un rattachement définitif
        // obligerait à recréer l'équipe — donc à perdre sa composition.
        $composant->call('rattacherEquipe', $agence->id, $equipe->id, true);
        $this->assertNull($equipe->fresh()->provider_agency_id);
    }

    // ─── Le filtre du centre de répartition ──────────────────────────────────────────────────

    #[Test]
    public function le_centre_de_repartition_filtre_par_implantation(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $bruxelles = ProviderAgency::create([
            'provider_organization_id' => $this->org->id,
            'name' => 'Bruxelles', 'slug' => 'bxl', 'status' => 'active',
        ]);
        $anvers = ProviderAgency::create([
            'provider_organization_id' => $this->org->id,
            'name' => 'Anvers', 'slug' => 'anv', 'status' => 'active',
        ]);

        $jour = now()->addDay()->setTime(9, 0);

        $mBruxelles = Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'provider_agency_id' => $bruxelles->id,
            'planned_start_at' => $jour,
        ]);

        // Celle-ci ne porte PAS d'agence en direct : elle en relève par l'équipe qui la tient. Ne
        // regarder que la colonne de la mission masquerait le cas normal.
        $equipeAnvers = FieldTeam::factory()->create([
            'organization_account_id' => $this->org->id,
            'provider_agency_id' => $anvers->id,
        ]);
        $mAnvers = Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'provider_agency_id' => null,
            'field_team_id' => $equipeAnvers->id,
            'planned_start_at' => $jour,
        ]);

        $composant = Livewire::actingAs($patron)
            ->test(DispatchCenter::class)
            ->set('filterDate', $jour->format('Y-m-d'));

        $this->assertEqualsCanonicalizing(
            [$mBruxelles->id, $mAnvers->id],
            $composant->instance()->missions->pluck('id')->all(),
            'Sans filtre, tout reste visible : la société mono-implantation ne doit rien perdre.',
        );

        $composant->set('filterAgencyId', $anvers->id);

        $this->assertSame(
            [$mAnvers->id],
            $composant->instance()->missions->pluck('id')->all(),
            'Le répartiteur d’Anvers ne fait plus défiler les interventions bruxelloises.',
        );
    }

    #[Test]
    public function un_identifiant_d_implantation_etranger_ne_filtre_rien(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $concurrent = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value, 'status' => 'active',
        ]);
        $adverse = ProviderAgency::create([
            'provider_organization_id' => $concurrent->id,
            'name' => 'Chez eux', 'slug' => 'chez-eux', 'status' => 'active',
        ]);

        $jour = now()->addDay()->setTime(9, 0);
        $mienne = Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'planned_start_at' => $jour,
        ]);

        // `filterAgencyId` est une propriété publique Livewire : le navigateur peut la retourner par `$set`.
        $composant = Livewire::actingAs($patron)
            ->test(DispatchCenter::class)
            ->set('filterDate', $jour->format('Y-m-d'))
            ->set('filterAgencyId', $adverse->id);

        $this->assertSame([$mienne->id], $composant->instance()->missions->pluck('id')->all());
    }
}
