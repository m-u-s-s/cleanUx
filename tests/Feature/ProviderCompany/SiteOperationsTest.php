<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\DispatchCenter;
use App\Livewire\ProviderCompany\SiteOperations;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\ProviderProfile;
use App\Models\ProviderSiteAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** LES SITES QU'UNE SOCIÉTÉ PRESTATAIRE DESSERT, ET QUI S'EN OCCUPE. POURQUOI CET ÉCRAN EXISTE. */
class SiteOperationsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: OrganizationAccount, 1: User} */
    private function societeAvec(OrganizationRole $role): array
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $user = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
        ]);

        return [$org, $user];
    }

    private function employe(OrganizationAccount $org, string $nom): User
    {
        $user = User::factory()->create(['name' => $nom]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::WORKER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user;
    }

    // ──────────────────────────────────────────────────────
    // La liste des sites desservis
    // ──────────────────────────────────────────────────────

    #[Test]
    public function un_site_ou_la_societe_intervient_apparait(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $site = OrganizationSite::factory()->create(['name' => 'Tour Madou']);
        Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'organization_site_id' => $site->id,
        ]);

        Livewire::actingAs($patron)
            ->test(SiteOperations::class)
            ->assertSee('Tour Madou');
    }

    #[Test]
    public function un_site_couvert_par_un_contrat_cadre_apparait_aussi(): void
    {
        // Une société peut être sous contrat sur un site avant d'y avoir envoyé la moindre
        // mission : c'est même l'état normal le jour de la signature.
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $client = OrganizationAccount::factory()->clientCompany()->create();
        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $client->id,
            'name' => 'Dépôt Vilvorde',
        ]);

        OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => $org->id,
        ]);

        Livewire::actingAs($patron)
            ->test(SiteOperations::class)
            ->assertSee('Dépôt Vilvorde');

        $this->assertNotNull($site->fresh());
    }

    #[Test]
    public function le_site_d_une_societe_concurrente_reste_invisible(): void
    {
        [, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $concurrente = OrganizationAccount::factory()->providerCompany()->create();
        $site = OrganizationSite::factory()->create(['name' => 'Chantier Confidentiel']);
        Mission::factory()->create([
            'provider_organization_id' => $concurrente->id,
            'organization_site_id' => $site->id,
        ]);

        Livewire::actingAs($patron)
            ->test(SiteOperations::class)
            ->assertDontSee('Chantier Confidentiel');
    }

    // ──────────────────────────────────────────────────────
    // Le référent
    // ──────────────────────────────────────────────────────

    #[Test]
    public function le_patron_designe_un_referent_sur_un_site(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $site = OrganizationSite::factory()->create();
        Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'organization_site_id' => $site->id,
        ]);

        $ana = $this->employe($org, 'Ana Silva');

        Livewire::actingAs($patron)
            ->test(SiteOperations::class)
            ->call('designerReferent', $site->id, $ana->id);

        $this->assertDatabaseHas('provider_site_assignments', [
            'provider_organization_id' => $org->id,
            'organization_site_id' => $site->id,
            'user_id' => $ana->id,
            'role' => ProviderSiteAssignment::ROLE_LEAD,
        ]);
    }

    #[Test]
    public function designer_un_referent_exige_sites_assign_members(): void
    {
        // La clé était déclarée dans la matrice depuis le début, consultée par personne. Un
        // dispatcheur répartit les missions du jour ; il ne redessine pas l'affectation durable des
        // équipes aux sites.
        [$org, $dispatcheur] = $this->societeAvec(OrganizationRole::DISPATCHER);

        $site = OrganizationSite::factory()->create();
        Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'organization_site_id' => $site->id,
        ]);

        $ana = $this->employe($org, 'Ana Silva');

        Livewire::actingAs($dispatcheur)
            ->test(SiteOperations::class)
            ->call('designerReferent', $site->id, $ana->id)
            ->assertForbidden();

        $this->assertDatabaseCount('provider_site_assignments', 0);
    }

    #[Test]
    public function on_ne_designe_pas_un_referent_sur_un_site_qu_on_ne_dessert_pas(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        // Aucun lien : ni mission, ni contrat. L'identifiant vient du client et ne suffit pas.
        $siteEtranger = OrganizationSite::factory()->create();
        $ana = $this->employe($org, 'Ana Silva');

        Livewire::actingAs($patron)
            ->test(SiteOperations::class)
            ->call('designerReferent', $siteEtranger->id, $ana->id);

        $this->assertDatabaseCount('provider_site_assignments', 0);
    }

    #[Test]
    public function on_ne_designe_pas_quelqu_un_qui_n_est_pas_de_la_maison(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $site = OrganizationSite::factory()->create();
        Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'organization_site_id' => $site->id,
        ]);

        $concurrente = OrganizationAccount::factory()->providerCompany()->create();
        $etranger = $this->employe($concurrente, 'Employé Concurrent');

        Livewire::actingAs($patron)
            ->test(SiteOperations::class)
            ->call('designerReferent', $site->id, $etranger->id);

        $this->assertDatabaseCount('provider_site_assignments', 0);
    }

    #[Test]
    public function redesigner_remplace_au_lieu_d_empiler(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $site = OrganizationSite::factory()->create();
        Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'organization_site_id' => $site->id,
        ]);

        $ana = $this->employe($org, 'Ana Silva');

        Livewire::actingAs($patron)
            ->test(SiteOperations::class)
            ->call('designerReferent', $site->id, $ana->id)
            ->call('designerReferent', $site->id, $ana->id);

        // La clé unique existe, mais une écriture non gardée lèverait une exception plutôt que de
        // remplacer : ce test dit que le geste est REJOUABLE, pas qu'il plante proprement.
        $this->assertDatabaseCount('provider_site_assignments', 1);
    }

    // ──────────────────────────────────────────────────────
    // La raison d'être du référent : la suggestion au répartiteur
    // ──────────────────────────────────────────────────────

    #[Test]
    public function le_referent_du_site_est_pre_suggere_au_repartiteur(): void
    {
        // Sans cela, désigner un référent serait de la décoration : une donnée saisie une fois, jamais relue, et qui se périme en silence.
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $site = OrganizationSite::factory()->create();
        $mission = Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'organization_site_id' => $site->id,
            'planned_start_at' => now()->addHours(3),
        ]);

        $ana = $this->employe($org, 'Ana Silva');
        ProviderSiteAssignment::create([
            'provider_organization_id' => $org->id,
            'organization_site_id' => $site->id,
            'user_id' => $ana->id,
            'role' => ProviderSiteAssignment::ROLE_LEAD,
        ]);

        Livewire::actingAs($patron)
            ->test(DispatchCenter::class)
            ->set('filterDate', now()->addHours(3)->format('Y-m-d'))
            ->call('startAssign', $mission->id)
            ->assertSet('assigneeId', $ana->id);
    }

    #[Test]
    public function sans_referent_le_repartiteur_choisit_lui_meme(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $site = OrganizationSite::factory()->create();
        $mission = Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'organization_site_id' => $site->id,
            'planned_start_at' => now()->addHours(3),
        ]);

        Livewire::actingAs($patron)
            ->test(DispatchCenter::class)
            ->call('startAssign', $mission->id)
            // `null` et non un premier venu : suggérer au hasard serait pire que ne rien suggérer.
            ->assertSet('assigneeId', null);
    }

    #[Test]
    public function le_referent_d_une_concurrente_n_est_jamais_suggere(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $site = OrganizationSite::factory()->create();
        $mission = Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'organization_site_id' => $site->id,
            'planned_start_at' => now()->addHours(3),
        ]);

        $concurrente = OrganizationAccount::factory()->providerCompany()->create();
        $leurReferent = $this->employe($concurrente, 'Referent Adverse');
        ProviderSiteAssignment::create([
            'provider_organization_id' => $concurrente->id,
            'organization_site_id' => $site->id,
            'user_id' => $leurReferent->id,
            'role' => ProviderSiteAssignment::ROLE_LEAD,
        ]);

        Livewire::actingAs($patron)
            ->test(DispatchCenter::class)
            ->call('startAssign', $mission->id)
            ->assertSet('assigneeId', null);
    }

    #[Test]
    public function deux_prestataires_du_meme_immeuble_ne_voient_pas_leurs_referents(): void
    {
        // Deux sociétés peuvent parfaitement desservir le même immeuble — l'une le nettoyage, l'autre les espaces verts.
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $site = OrganizationSite::factory()->create(['name' => 'Immeuble Partagé']);
        Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'organization_site_id' => $site->id,
        ]);

        $concurrente = OrganizationAccount::factory()->providerCompany()->create();
        Mission::factory()->create([
            'provider_organization_id' => $concurrente->id,
            'organization_site_id' => $site->id,
        ]);
        $leurReferent = $this->employe($concurrente, 'Referent Adverse');
        ProviderSiteAssignment::create([
            'provider_organization_id' => $concurrente->id,
            'organization_site_id' => $site->id,
            'user_id' => $leurReferent->id,
            'role' => ProviderSiteAssignment::ROLE_LEAD,
        ]);

        Livewire::actingAs($patron)
            ->test(SiteOperations::class)
            ->assertSee('Immeuble Partagé')
            ->assertDontSee('Referent Adverse');
    }
}
