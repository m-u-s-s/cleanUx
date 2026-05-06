<?php

namespace Tests\Feature\Broadcasting;

use App\Models\Channel;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 — Tests des autorisations Broadcasting.
 *
 * Vérifie qu'un user ne peut écouter que ses propres canaux.
 * Empêche la régression du bug "channel.{id} non autorisé" qui rendait
 * le chat équipe inopérant en production.
 */
class PrivateChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Charger les channels d'autorisation
        require base_path('routes/channels.php');
    }

    public function test_user_can_authorize_private_channel_when_member(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create(['organization_account_id' => $org->id]);
        $channel = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'Authorized chan',
            'type' => Channel::TYPE_TEAM,
            'is_private' => false,
            'created_by' => $user->id,
        ]);

        // Faire de l'utilisateur un membre du canal
        $channel->members()->attach($user->id);

        $this->actingAs($user);

        // Simuler la requête d'auth broadcasting
        $response = $this->postJson('/broadcasting/auth', [
            'socket_id'    => '12345.67890',
            'channel_name' => 'private-channel.' . $channel->id,
        ]);

        $response->assertStatus(200);
    }

    public function test_user_cannot_authorize_private_channel_when_not_member(): void
    {
        $org = OrganizationAccount::factory()->create();
        $owner = User::factory()->create(['organization_account_id' => $org->id]);
        $intruder = User::factory()->create(['organization_account_id' => $org->id]);

        $channel = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'Restricted chan',
            'type' => Channel::TYPE_TEAM,
            'is_private' => true,
            'created_by' => $owner->id,
        ]);
        $channel->members()->attach($owner->id);
        // intruder n'est PAS membre

        $this->actingAs($intruder);

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id'    => '12345.67890',
            'channel_name' => 'private-channel.' . $channel->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_authorize_their_own_user_channel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id'    => '12345.67890',
            'channel_name' => 'private-user.' . $user->id,
        ]);

        $response->assertStatus(200);
    }

    public function test_user_cannot_authorize_another_users_channel(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA);

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id'    => '12345.67890',
            'channel_name' => 'private-user.' . $userB->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_join_org_presence_channel(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create(['organization_account_id' => $org->id]);

        $this->actingAs($user);

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id'    => '12345.67890',
            'channel_name' => 'presence-presence-org.' . $org->id,
        ]);

        // Une presence channel renvoie 200 + les meta de l'utilisateur
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'channel_data' => [],
        ]);
    }

    public function test_user_cannot_join_other_org_presence_channel(): void
    {
        $orgA = OrganizationAccount::factory()->create();
        $orgB = OrganizationAccount::factory()->create();
        $user = User::factory()->create(['organization_account_id' => $orgA->id]);

        $this->actingAs($user);

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id'    => '12345.67890',
            'channel_name' => 'presence-presence-org.' . $orgB->id,
        ]);

        $response->assertStatus(403);
    }
}
