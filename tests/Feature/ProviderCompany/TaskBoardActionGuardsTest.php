<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ProviderCompany\TaskBoard;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LES ACTIONS DU TABLEAU DE TÂCHES N'ÉTAIENT GARDÉES QU'AU MONTAGE.
 *
 * `TaskBoard::mount()` vérifie une permission de lecture, puis `createTask()` et `updateStatus()`
 * n'en vérifiaient plus aucune : un `viewer` — rôle explicitement en lecture seule — pouvait créer
 * des tâches et déplacer celles des autres. Les clés `tasks.create` et `tasks.assign` existaient
 * pourtant dans la matrice de `PermissionService` sans qu'aucun appelant ne les consulte, comme
 * `channels.manage` avant elles.
 *
 * `createTask()` cachait un second défaut : `assigneeIds` arrivait du navigateur et était attaché
 * sans vérification. On pouvait donc assigner une tâche à un utilisateur d'une AUTRE société.
 *
 * À l'inverse — et contrairement à ce qu'annonçait le programme — `deleteTask()` était DÉJÀ gardé
 * (créateur, ou `tasks.close`). Il n'est pas touché.
 */
class TaskBoardActionGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function membre(OrganizationAccount $org, OrganizationRole $role): User
    {
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

        return $user;
    }

    /**
     * J'AI D'ABORD ÉCRIT CE TEST À L'ENVERS.
     *
     * Je supposais qu'un `viewer` pouvait créer des tâches, faute de garde sur l'action. En fait
     * `mount()` exige déjà `tasks.create` : un rôle en lecture seule n'ouvre même pas l'écran. La
     * protection existait, ailleurs que là où je la cherchais. On la fige telle qu'elle est.
     */
    #[Test]
    public function un_viewer_n_ouvre_meme_pas_le_tableau(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $viewer = $this->membre($org, OrganizationRole::VIEWER);

        Livewire::actingAs($viewer)
            ->test(TaskBoard::class)
            ->assertForbidden();
    }

    /**
     * CE QUE LA GARDE SUR L'ACTION APPORTE VRAIMENT.
     *
     * Puisque `mount()` filtre déjà, on pourrait croire les gardes d'action redondantes. Elles ne
     * le sont pas : Livewire ne rejoue PAS `mount()` aux requêtes suivantes. Un droit retiré en
     * cours de session laissait donc la personne continuer d'écrire tant que son onglet restait
     * ouvert. C'est ce trou-là que ce test ferme.
     */
    #[Test]
    public function un_droit_retire_apres_le_montage_ne_permet_plus_d_ecrire(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patron = $this->membre($org, OrganizationRole::OWNER);
        $equipier = $this->membre($org, OrganizationRole::TEAM_LEAD);

        $tache = Task::create([
            'organization_account_id' => $org->id,
            'created_by' => $patron->id,
            'title' => 'Intervention à planifier',
            'priority' => 'medium',
            'status' => Task::STATUS_TODO,
        ]);

        $composant = Livewire::actingAs($equipier)->test(TaskBoard::class);

        // Le responsable rétrograde l'équipier pendant que son onglet reste ouvert.
        OrganizationMember::where('organization_account_id', $org->id)
            ->where('user_id', $equipier->id)
            ->update(['role' => OrganizationRole::VIEWER->value]);

        app(PermissionService::class)->invalidateCache($equipier->id, $org->id);

        $composant->call('updateStatus', $tache->id, Task::STATUS_DONE)
            ->assertForbidden();

        $this->assertSame(
            Task::STATUS_TODO,
            $tache->fresh()->status,
            "Sans garde sur l'action, l'onglet déjà ouvert continuait d'écrire après le retrait du droit.",
        );
    }

    #[Test]
    public function on_n_assigne_pas_une_tache_a_un_membre_d_une_autre_societe(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patron = $this->membre($org, OrganizationRole::OWNER);

        $autreOrg = OrganizationAccount::factory()->providerCompany()->create();
        $etranger = $this->membre($autreOrg, OrganizationRole::WORKER);

        Livewire::actingAs($patron)
            ->test(TaskBoard::class)
            ->set('title', 'Tâche à assigner')
            ->set('priority', 'medium')
            ->set('assigneeIds', [$etranger->id])
            ->call('createTask');

        $tache = Task::where('title', 'Tâche à assigner')->first();

        $this->assertNotNull($tache, 'Le propriétaire doit pouvoir créer une tâche.');
        $this->assertEmpty(
            $tache->assignees()->pluck('users.id')->all(),
            "Les identifiants d'assignation viennent du navigateur : ils doivent être vérifiés.",
        );
    }

    #[Test]
    public function le_proprietaire_cree_et_deplace_normalement(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patron = $this->membre($org, OrganizationRole::OWNER);

        Livewire::actingAs($patron)
            ->test(TaskBoard::class)
            ->set('title', 'Tâche légitime')
            ->set('priority', 'high')
            ->call('createTask');

        $tache = Task::where('title', 'Tâche légitime')->firstOrFail();

        Livewire::actingAs($patron)
            ->test(TaskBoard::class)
            ->call('updateStatus', $tache->id, Task::STATUS_DONE);

        $this->assertSame(Task::STATUS_DONE, $tache->fresh()->status);
    }
}
