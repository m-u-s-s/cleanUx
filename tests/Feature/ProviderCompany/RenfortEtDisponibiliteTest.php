<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\DispatchCenter;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** DEUX PERSONNES SUR UNE MÊME MISSION, ET UN SEUL RESPONSABLE. */
class RenfortEtDisponibiliteTest extends TestCase
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

        ProviderProfile::factory()->create([
            'user_id' => $patron->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
        ]);

        return [$org, $patron];
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

    #[Test]
    public function un_renfort_s_ajoute_sans_deloger_le_responsable(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        $mission = Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => now()->addHours(3),
        ]);

        $ana = $this->employe($org, 'Ana Silva');
        $bruno = $this->employe($org, 'Bruno Costa');

        Livewire::actingAs($patron)
            ->test(DispatchCenter::class)
            ->call('startAssign', $mission->id)
            ->set('assigneeId', $ana->id)
            ->call('confirmAssign')
            ->call('ajouterRenfort', $mission->id, $bruno->id);

        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'user_id' => $ana->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
        ]);

        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'user_id' => $bruno->id,
            'role_on_mission' => 'helper',
            'assignment_status' => 'assigned',
        ]);

        // Le responsable reste UNIQUE et synchronisé : c'est lui que lisent le tableau de bord,
        // l'autorisation Reverb `mission.{id}` et le suivi de trajet.
        $this->assertSame($ana->id, $mission->fresh()->lead_provider_user_id);
    }

    #[Test]
    public function changer_de_responsable_ne_renvoie_pas_les_renforts_chez_eux(): void
    {
        // LE DÉFAUT QUE CE LOT AURAIT INTRODUIT SANS CE TEST.
        [$org, $patron] = $this->societeAvecPatron();

        $mission = Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => now()->addHours(3),
        ]);

        $ana = $this->employe($org, 'Ana Silva');
        $bruno = $this->employe($org, 'Bruno Costa');
        $chloe = $this->employe($org, 'Chloé Martin');

        $composant = Livewire::actingAs($patron)
            ->test(DispatchCenter::class)
            ->call('startAssign', $mission->id)
            ->set('assigneeId', $ana->id)
            ->call('confirmAssign')
            ->call('ajouterRenfort', $mission->id, $bruno->id);

        // Chloé remplace Ana comme responsable.
        $composant
            ->call('startAssign', $mission->id)
            ->set('assigneeId', $chloe->id)
            ->call('confirmAssign');

        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'user_id' => $chloe->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
        ]);

        // Ana est libérée — `reassigned`, pas `cancelled` : un remplacement n'est pas un abandon.
        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'user_id' => $ana->id,
            'assignment_status' => 'reassigned',
        ]);

        // Bruno, lui, travaille toujours.
        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'user_id' => $bruno->id,
            'role_on_mission' => 'helper',
            'assignment_status' => 'assigned',
        ]);
    }

    #[Test]
    public function un_renfort_exige_la_meme_permission_que_l_assignation(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        $mission = Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => now()->addHours(3),
        ]);

        $concurrente = OrganizationAccount::factory()->providerCompany()->create();
        $etranger = $this->employe($concurrente, 'Employé Concurrent');

        Livewire::actingAs($patron)
            ->test(DispatchCenter::class)
            ->call('ajouterRenfort', $mission->id, $etranger->id);

        // On n'envoie pas en renfort quelqu'un qui n'est pas de la maison.
        $this->assertDatabaseCount('mission_assignments', 0);
    }

    #[Test]
    public function un_renfort_se_retire(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        $mission = Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => now()->addHours(3),
        ]);

        $bruno = $this->employe($org, 'Bruno Costa');

        Livewire::actingAs($patron)
            ->test(DispatchCenter::class)
            ->call('ajouterRenfort', $mission->id, $bruno->id)
            ->call('retirerRenfort', $mission->id, $bruno->id);

        $ligne = MissionAssignment::where('mission_id', $mission->id)
            ->where('user_id', $bruno->id)
            ->first();

        // La ligne SURVIT, au statut `released` : l'historique d'une mission doit dire qui y a été
        // affecté, même brièvement. Supprimer effacerait la trace.
        $this->assertNotNull($ligne);
        $this->assertSame('released', $ligne->assignment_status);
    }

    #[Test]
    public function la_disponibilite_distingue_qui_est_libre_de_qui_est_deja_pris(): void
    {
        // CE TEST ASSERTAIT D'ABORD `assertIsBool`, ET IL PASSAIT SUR UNE IMPLÉMENTATION FAUSSE.
        [$org, $patron] = $this->societeAvecPatron();

        $debut = now()->addHours(3);

        $mission = Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => $debut,
            'planned_end_at' => $debut->copy()->addHours(2),
        ]);

        $libre = $this->employe($org, 'Ana Libre');
        $prise = $this->employe($org, 'Bea Occupee');

        // Béa travaille déjà ailleurs sur exactement ce créneau.
        $autreMission = Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => $debut,
            'planned_end_at' => $debut->copy()->addHours(2),
        ]);
        MissionAssignment::create([
            'mission_id' => $autreMission->id,
            'user_id' => $prise->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $composant = Livewire::actingAs($patron)
            ->test(DispatchCenter::class)
            ->set('filterDate', $debut->format('Y-m-d'))
            ->call('startAssign', $mission->id);

        $disponibilites = $composant->viewData('disponibilites');

        $this->assertTrue($disponibilites[$libre->id], 'Ana n’a rien d’autre : elle est libre.');
        $this->assertFalse($disponibilites[$prise->id], 'Béa est déjà sur une autre mission.');

        // Et cela n'empêche RIEN : l'assignation d'une personne occupée aboutit quand même.
        $composant->set('assigneeId', $prise->id)->call('confirmAssign');

        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'user_id' => $prise->id,
            'assignment_status' => 'assigned',
        ]);
    }
}
