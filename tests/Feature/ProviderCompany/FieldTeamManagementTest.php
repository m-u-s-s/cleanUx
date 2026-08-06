<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ProviderCompany\FieldTeams;
use App\Models\FieldTeam;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UNE SOCIÉTÉ PRESTATAIRE DOIT POUVOIR ORGANISER SES PROPRES AGENCES.
 *
 * POURQUOI CE FICHIER EXISTE. Le modèle `FieldTeam` existe et porte tout ce qu'il faut —
 * organisation, zone de service, chef d'équipe, capacité maximale, statut. Mais il n'est piloté
 * que depuis les écrans d'ADMINISTRATION de la plateforme (`Admin/GestionEquipesPartenaires`,
 * `Admin/B2BOperationsCenter`, `Admin/OrchestrationTerrainCenter`) et un écran employé.
 *
 * Vérifié : les cinq écrans de l'espace société prestataire sont `dashboard`, `channels`, `tasks`,
 * `dispatch` et `team` (les MEMBRES, pas les équipes). Une société voulant ouvrir une agence, la
 * rattacher à une zone ou en nommer le responsable devait donc passer par un administrateur.
 *
 * Ce n'est pas une capacité manquante en base : c'est une capacité existante sans porte d'entrée
 * pour celui qu'elle concerne.
 */
class FieldTeamManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: OrganizationAccount, 1: User} */
    private function societeAvecPatron(): array
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $patron = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $patron->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return [$org, $patron];
    }

    #[Test]
    public function une_societe_cree_sa_propre_equipe(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        Livewire::actingAs($patron)
            ->test(FieldTeams::class)
            ->set('nom', 'Agence Nord')
            ->set('capaciteMax', 4)
            ->call('creer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('field_teams', [
            'organization_account_id' => $org->id,
            'name' => 'Agence Nord',
            'max_concurrent_missions' => 4,
        ]);
    }

    #[Test]
    public function la_liste_ne_montre_que_les_equipes_de_sa_societe(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        FieldTeam::create([
            'organization_account_id' => $org->id,
            'name' => 'Mon agence',
            'slug' => 'mon-agence',
            'status' => 'active',
        ]);

        $autreOrg = OrganizationAccount::factory()->providerCompany()->create();
        FieldTeam::create([
            'organization_account_id' => $autreOrg->id,
            'name' => 'Agence concurrente',
            'slug' => 'agence-concurrente',
            'status' => 'active',
        ]);

        Livewire::actingAs($patron)
            ->test(FieldTeams::class)
            ->assertSee('Mon agence')
            ->assertDontSee('Agence concurrente');
    }

    #[Test]
    public function on_ne_renomme_pas_l_equipe_d_une_autre_societe(): void
    {
        [, $patron] = $this->societeAvecPatron();

        $autreOrg = OrganizationAccount::factory()->providerCompany()->create();
        $etrangere = FieldTeam::create([
            'organization_account_id' => $autreOrg->id,
            'name' => 'Agence concurrente',
            'slug' => 'agence-concurrente',
            'status' => 'active',
        ]);

        Livewire::actingAs($patron)
            ->test(FieldTeams::class)
            ->call('archiver', $etrangere->id);

        $this->assertSame(
            'active',
            $etrangere->fresh()->status,
            "L'identifiant vient du navigateur : il ne doit jamais désigner l'équipe d'autrui.",
        );
    }

    #[Test]
    public function un_role_sans_droit_ne_cree_pas_d_equipe(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $viewer = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $viewer->id,
            'role' => OrganizationRole::VIEWER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        Livewire::actingAs($viewer)
            ->test(FieldTeams::class)
            ->assertForbidden();
    }
}
