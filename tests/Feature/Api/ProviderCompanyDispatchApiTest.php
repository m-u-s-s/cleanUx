<?php

namespace Tests\Feature\Api;

use App\Enums\OrganizationRole;
use App\Models\Booking;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RÉPARTITION ET CANAUX EN API — LES DEUX DERNIERS DOMAINES DE L'ESPACE SOCIÉTÉ.
 *
 * L'assignation partage `MissionAssignmentService` avec l'écran web : la règle « réassigner, c'est
 * aussi désassigner » est délicate — libérer les leads actifs des autres, puis synchroniser
 * `lead_provider_user_id` — et deux copies auraient divergé au premier ajustement.
 *
 * Les canaux passent par `ChannelPolicy` en LECTURE comme en ÉCRITURE. C'est en écrivant cette API
 * qu'est apparu le défaut corrigé le même jour : côté web, `sendMessage()` ne consultait aucune
 * politique, si bien qu'on pouvait publier dans le canal privé d'une autre société.
 */
class ProviderCompanyDispatchApiTest extends TestCase
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

        return [$org, $user];
    }

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

    #[Test]
    public function les_missions_de_la_societe_sont_listees(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        Mission::create([
            'booking_id' => Booking::factory()->create()->id,
            'status' => 'planned',
            'provider_organization_id' => $org->id,
            'planned_start_at' => now()->addDay(),
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/provider/company/missions')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'planned');
    }

    #[Test]
    public function assigner_libere_l_ancien_travailleur_et_synchronise_le_lead(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $premier = $this->membre($org, OrganizationRole::WORKER);
        $second = $this->membre($org, OrganizationRole::WORKER);

        $mission = Mission::create([
            'booking_id' => Booking::factory()->create()->id,
            'status' => 'planned',
            'provider_organization_id' => $org->id,
            'planned_start_at' => now()->addDay(),
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->postJson("/api/provider/company/missions/{$mission->id}/assign", ['user_id' => $premier->id])
            ->assertOk();

        $this->postJson("/api/provider/company/missions/{$mission->id}/assign", ['user_id' => $second->id])
            ->assertOk();

        $this->assertSame(
            $second->id,
            $mission->fresh()->lead_provider_user_id,
            'Le lead doit suivre la dernière assignation.',
        );

        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'user_id' => $premier->id,
            'assignment_status' => 'reassigned',
        ]);
    }

    #[Test]
    public function on_n_assigne_pas_a_l_employe_d_une_autre_societe(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $autreOrg = OrganizationAccount::factory()->providerCompany()->create();
        $etranger = $this->membre($autreOrg, OrganizationRole::WORKER);

        $mission = Mission::create([
            'booking_id' => Booking::factory()->create()->id,
            'status' => 'planned',
            'provider_organization_id' => $org->id,
            'planned_start_at' => now()->addDay(),
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->postJson("/api/provider/company/missions/{$mission->id}/assign", ['user_id' => $etranger->id])
            ->assertNotFound();

        $this->assertNull($mission->fresh()->lead_provider_user_id);
    }

    #[Test]
    public function les_canaux_listes_sont_ceux_dont_on_est_membre(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $sien = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'general',
            'type' => Channel::TYPE_TEAM,
            'is_private' => false,
            'created_by' => $patron->id,
        ]);
        $sien->members()->attach($patron->id, ['role' => 'owner']);

        // Même société, mais canal dont il n'est pas membre.
        Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'direction',
            'type' => Channel::TYPE_TEAM,
            'is_private' => true,
            'created_by' => $this->membre($org, OrganizationRole::WORKER)->id,
        ]);

        Sanctum::actingAs($patron, ['*']);

        $reponse = $this->getJson('/api/provider/company/channels')->assertOk();

        $reponse->assertJsonPath('data.0.name', 'general');
        $reponse->assertJsonMissing(['name' => 'direction']);
    }

    #[Test]
    public function on_ne_lit_pas_les_messages_du_canal_d_une_autre_societe(): void
    {
        [, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $autreOrg = OrganizationAccount::factory()->providerCompany()->create();
        $concurrent = $this->membre($autreOrg, OrganizationRole::OWNER);

        $canalConcurrent = Channel::create([
            'organization_account_id' => $autreOrg->id,
            'name' => 'strategie',
            'type' => Channel::TYPE_TEAM,
            'is_private' => true,
            'created_by' => $concurrent->id,
        ]);
        $canalConcurrent->members()->attach($concurrent->id, ['role' => 'owner']);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson("/api/provider/company/channels/{$canalConcurrent->id}/messages")
            ->assertNotFound();
    }

    #[Test]
    public function on_publie_dans_son_canal_et_pas_dans_celui_d_autrui(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $canal = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'general',
            'type' => Channel::TYPE_TEAM,
            'is_private' => false,
            'created_by' => $patron->id,
        ]);
        $canal->members()->attach($patron->id, ['role' => 'owner']);

        Sanctum::actingAs($patron, ['*']);

        $this->postJson("/api/provider/company/channels/{$canal->id}/messages", [
            'content' => 'Message depuis le mobile.',
        ])->assertCreated();

        $this->assertSame(1, Message::where('channel_id', $canal->id)->count());

        // Canal d'une autre société : jamais chargé, donc jamais écrit.
        $autreOrg = OrganizationAccount::factory()->providerCompany()->create();
        $canalEtranger = Channel::create([
            'organization_account_id' => $autreOrg->id,
            'name' => 'prive',
            'type' => Channel::TYPE_TEAM,
            'is_private' => true,
            'created_by' => $this->membre($autreOrg, OrganizationRole::OWNER)->id,
        ]);

        $this->postJson("/api/provider/company/channels/{$canalEtranger->id}/messages", [
            'content' => 'Intrusion.',
        ])->assertNotFound();

        $this->assertSame(0, Message::where('channel_id', $canalEtranger->id)->count());
    }
}
