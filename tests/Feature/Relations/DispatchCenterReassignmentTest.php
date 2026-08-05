<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationRole;
use App\Livewire\ProviderCompany\DispatchCenter;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SP3 §3 / DoD 6 — quand une société est choisie, la mission porte
 * provider_organization_id = l'org et le DispatchCenter société doit pouvoir
 * RÉASSIGNER un autre worker de la même org.
 *
 * On part d'une mission auto-suggérée (provider_organization_id rempli + un
 * lead initial workerA) et on prouve, via le composant Livewire réel, qu'un
 * dispatcher de l'org peut la réassigner à workerB de la même org.
 */
class DispatchCenterReassignmentTest extends TestCase
{
    use RefreshDatabase;

    private function member(int $orgId, OrganizationRole $role): User
    {
        $user = User::factory()->create(['current_organization_id' => $orgId]);

        OrganizationMember::create([
            'organization_account_id' => $orgId,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_dispatcher_can_reassign_mission_to_another_worker_of_the_same_org(): void
    {
        $org = OrganizationAccount::factory()->create();

        $dispatcher = $this->member($org->id, OrganizationRole::OWNER); // a missions.dispatch
        $workerA = $this->member($org->id, OrganizationRole::WORKER);
        $workerB = $this->member($org->id, OrganizationRole::WORKER);

        $booking = Booking::factory()->create();

        // Mission auto-suggérée : portée par l'org + lead initial = workerA.
        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'provider_organization_id' => $org->id,
            'lead_provider_user_id' => $workerA->id,
            'planned_start_at' => now(),
        ]);

        MissionAssignment::create([
            'mission_id' => $mission->id,
            'user_id' => $workerA->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
        ]);

        // Le dispatcher réassigne la mission à workerB via le composant réel.
        Livewire::actingAs($dispatcher)
            ->test(DispatchCenter::class)
            ->set('filterDate', '')
            ->call('startAssign', $mission->id)
            ->set('assigneeId', $workerB->id)
            ->call('confirmAssign')
            ->assertHasNoErrors();

        // workerB est désormais assigné à la mission de l'org.
        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'user_id' => $workerB->id,
            'assignment_status' => 'assigned',
        ]);

        $this->assertSame('assigned', $mission->fresh()->status);
    }

    /**
     * RÉASSIGNER, C'EST AUSSI DÉSASSIGNER.
     *
     * `confirmAssign()` créait le nouvel assignment sans toucher à l'ancien : la mission se
     * retrouvait avec DEUX assignments actifs, et `missions.lead_provider_user_id` continuait de
     * désigner le travailleur remplacé. Trois conséquences en chaîne :
     *
     *   - le tableau de bord affiche l'ancien (il lit `leadProvider`) ;
     *   - l'autorisation Reverb `mission.{id}` reste ouverte à celui qui n'y est plus ;
     *   - le suivi de trajet et la présence continuent de le viser.
     */
    public function test_reassigner_libere_l_ancien_travailleur_et_synchronise_le_lead(): void
    {
        $org = OrganizationAccount::factory()->create();

        $dispatcher = $this->member($org->id, OrganizationRole::OWNER);
        $workerA = $this->member($org->id, OrganizationRole::WORKER);
        $workerB = $this->member($org->id, OrganizationRole::WORKER);

        $mission = Mission::create([
            'booking_id' => Booking::factory()->create()->id,
            'status' => 'planned',
            'provider_organization_id' => $org->id,
            'lead_provider_user_id' => $workerA->id,
            'planned_start_at' => now(),
        ]);

        MissionAssignment::create([
            'mission_id' => $mission->id,
            'user_id' => $workerA->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($dispatcher)
            ->test(DispatchCenter::class)
            ->set('filterDate', '')
            ->call('startAssign', $mission->id)
            ->set('assigneeId', $workerB->id)
            ->call('confirmAssign')
            ->assertHasNoErrors();

        $this->assertSame(
            'reassigned',
            MissionAssignment::where('mission_id', $mission->id)
                ->where('user_id', $workerA->id)
                ->value('assignment_status'),
            "L'ancien travailleur est resté assigné : la mission a deux assignments actifs.",
        );

        $this->assertSame(
            $workerB->id,
            $mission->fresh()->lead_provider_user_id,
            'Le lead pointe encore le travailleur remplacé : tableau de bord, Reverb et suivi le visent.',
        );
    }
}
