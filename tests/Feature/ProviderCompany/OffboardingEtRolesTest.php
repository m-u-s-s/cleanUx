<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\TeamManagement;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationRolePermission;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * QUAND QUELQU'UN PART, ET QUI PEUT DÉCLASSER QUI.
 *
 * DEUX DÉFAUTS DISTINCTS, tous deux invisibles tant qu'on ne regarde que l'écran :
 *
 *   1. LE DÉPART NE DÉFAISAIT RIEN. `remove()` passait l'adhésion à `left` et s'arrêtait là. Les
 *      missions de la semaine suivante restaient assignées à quelqu'un qui ne viendra pas — le
 *      répartiteur les voyait « couvertes », et le client découvrait l'absence le jour même. La
 *      personne restait aussi dans les canaux d'équipe, à lire les échanges de son ancien
 *      employeur.
 *
 *   2. LA GARDE DE RÔLE NE REGARDAIT QUE LA MOITIÉ DE LA QUESTION. `changeRole()` comparait le
 *      rang du NOUVEAU rôle à celui de l'acteur, jamais le rang ACTUEL de la cible. Un responsable
 *      d'exploitation pouvait donc déclasser un propriétaire en nettoyeur : le rôle visé est de
 *      rang inférieur au sien, la condition passait. `memberSousGarde()` faisait exactement ce
 *      contrôle et cette méthode ne l'appelait pas — elle passait par `getOrgMember()`.
 */
class OffboardingEtRolesTest extends TestCase
{
    use RefreshDatabase;

    private function membre(OrganizationAccount $org, OrganizationRole $role, string $nom): User
    {
        $user = User::factory()->create([
            'name' => $nom,
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

        return $user;
    }

    // ──────────────────────────────────────────────────────
    // La garde bidirectionnelle
    // ──────────────────────────────────────────────────────

    #[Test]
    public function un_responsable_ne_peut_pas_declasser_un_proprietaire(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        // DEUX propriétaires : la garde « dernier propriétaire » ne peut donc pas expliquer un
        // refus. Ce test isole bien la hiérarchie, et rien d'autre.
        $patronne = $this->membre($org, OrganizationRole::OWNER, 'Patronne');
        $secondPatron = $this->membre($org, OrganizationRole::OWNER, 'Second Patron');

        $responsable = $this->membre($org, OrganizationRole::OPERATIONS_MANAGER, 'Responsable');

        /*
         * CE TEST PASSAIT D'ABORD POUR LA MAUVAISE RAISON.
         *
         * `OPERATIONS_MANAGER` n'a pas `members.edit_role` par défaut : c'était donc la PERMISSION
         * qui refusait, et la hiérarchie n'était jamais atteinte. Un vert qui ne prouve rien —
         * exactement le motif que ce dépôt répète.
         *
         * On accorde donc le droit, ce qu'une société peut parfaitement faire depuis que la matrice
         * est réglable. Il ne reste alors qu'une chose capable de refuser : le rang.
         */
        OrganizationRolePermission::create([
            'organization_account_id' => $org->id,
            'role' => OrganizationRole::OPERATIONS_MANAGER->value,
            'permission' => 'members.edit_role',
            'granted' => true,
        ]);

        $cible = OrganizationMember::where('user_id', $secondPatron->id)->firstOrFail();

        Livewire::actingAs($responsable)
            ->test(TeamManagement::class)
            ->call('changeRole', $cible->id, OrganizationRole::WORKER->value);

        $this->assertSame(
            OrganizationRole::OWNER,
            $cible->fresh()->role,
            'Un rang inférieur ne déclasse pas un rang supérieur, quel que soit le rôle visé.'
        );

        $this->assertNotNull($patronne->fresh());
    }

    #[Test]
    public function le_proprietaire_declasse_bien_son_responsable(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $patronne = $this->membre($org, OrganizationRole::OWNER, 'Patronne');
        $responsable = $this->membre($org, OrganizationRole::OPERATIONS_MANAGER, 'Responsable');

        $cible = OrganizationMember::where('user_id', $responsable->id)->firstOrFail();

        Livewire::actingAs($patronne)
            ->test(TeamManagement::class)
            ->call('changeRole', $cible->id, OrganizationRole::WORKER->value);

        $this->assertSame(OrganizationRole::WORKER, $cible->fresh()->role);
    }

    // ──────────────────────────────────────────────────────
    // Le départ
    // ──────────────────────────────────────────────────────

    #[Test]
    public function le_depart_libere_les_missions_a_venir(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patronne = $this->membre($org, OrganizationRole::OWNER, 'Patronne');
        $partant = $this->membre($org, OrganizationRole::WORKER, 'Partant');

        $missionFuture = Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => now()->addDays(3),
            'lead_provider_user_id' => $partant->id,
        ]);
        MissionAssignment::create([
            'mission_id' => $missionFuture->id,
            'user_id' => $partant->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $cible = OrganizationMember::where('user_id', $partant->id)->firstOrFail();

        Livewire::actingAs($patronne)
            ->test(TeamManagement::class)
            ->call('remove', $cible->id);

        // La mission RETOURNE au dispatch : `lead_provider_user_id` est libéré, sans quoi le
        // tableau de bord, l'autorisation Reverb et le suivi de trajet viseraient toujours
        // quelqu'un qui ne viendra pas.
        $this->assertNull($missionFuture->fresh()->lead_provider_user_id);

        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $missionFuture->id,
            'user_id' => $partant->id,
            'assignment_status' => 'released',
        ]);
    }

    #[Test]
    public function le_depart_ne_reecrit_pas_le_travail_deja_fait(): void
    {
        /*
         * L'HISTORIQUE EST INTOUCHABLE. Une mission passée dit qui l'a réalisée ; la réécrire
         * parce que la personne a quitté l'entreprise fausserait la facturation, les évaluations
         * client et toute réclamation ultérieure.
         */
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patronne = $this->membre($org, OrganizationRole::OWNER, 'Patronne');
        $partant = $this->membre($org, OrganizationRole::WORKER, 'Partant');

        $missionPassee = Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => now()->subDays(5),
            'lead_provider_user_id' => $partant->id,
        ]);
        MissionAssignment::create([
            'mission_id' => $missionPassee->id,
            'user_id' => $partant->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now()->subDays(6),
        ]);

        $cible = OrganizationMember::where('user_id', $partant->id)->firstOrFail();

        Livewire::actingAs($patronne)
            ->test(TeamManagement::class)
            ->call('remove', $cible->id);

        $this->assertSame($partant->id, $missionPassee->fresh()->lead_provider_user_id);
        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $missionPassee->id,
            'user_id' => $partant->id,
            'assignment_status' => 'assigned',
        ]);
    }

    #[Test]
    public function le_depart_retire_des_canaux_d_equipe(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patronne = $this->membre($org, OrganizationRole::OWNER, 'Patronne');
        $partant = $this->membre($org, OrganizationRole::WORKER, 'Partant');

        $canal = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'Général',
            'type' => 'team',
            'created_by' => $patronne->id,
        ]);
        $canal->members()->attach([
            $patronne->id => ['role' => 'owner'],
            $partant->id => ['role' => 'member'],
        ]);

        $cible = OrganizationMember::where('user_id', $partant->id)->firstOrFail();

        Livewire::actingAs($patronne)
            ->test(TeamManagement::class)
            ->call('remove', $cible->id);

        // Sans cela, un ancien salarié continue de lire les échanges de son ex-employeur — et
        // l'autorisation Reverb, qui vérifie l'appartenance au canal, la lui accorde.
        $this->assertSame([$patronne->id], $canal->members()->pluck('users.id')->all());
    }

    #[Test]
    public function les_messages_du_partant_restent_lisibles(): void
    {
        // Retirer quelqu'un d'un canal ne doit pas trouer la conversation des autres : un fil dont
        // la moitié des messages disparaît devient illisible pour ceux qui restent.
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patronne = $this->membre($org, OrganizationRole::OWNER, 'Patronne');
        $partant = $this->membre($org, OrganizationRole::WORKER, 'Partant');

        $canal = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'Général',
            'type' => 'team',
            'created_by' => $patronne->id,
        ]);
        $canal->members()->attach([
            $patronne->id => ['role' => 'owner'],
            $partant->id => ['role' => 'member'],
        ]);

        Message::create([
            'channel_id' => $canal->id,
            'user_id' => $partant->id,
            'content' => 'Le code de la porte a changé',
            'type' => 'text',
        ]);

        $cible = OrganizationMember::where('user_id', $partant->id)->firstOrFail();

        Livewire::actingAs($patronne)
            ->test(TeamManagement::class)
            ->call('remove', $cible->id);

        $this->assertDatabaseHas('messages', [
            'channel_id' => $canal->id,
            'user_id' => $partant->id,
            'content' => 'Le code de la porte a changé',
        ]);
    }
}
