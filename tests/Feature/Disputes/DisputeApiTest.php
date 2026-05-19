<?php

namespace Tests\Feature\Disputes;

use App\Models\ComplaintCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DisputeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_open_dispute_via_api(): void
    {
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);

        $response = $this->postJson('/api/client/disputes', [
            'subject' => 'Service problématique',
            'description' => 'Le service rendu ne correspond pas à la commande.',
            'category' => 'quality',
            'priority' => 'high',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'reference', 'status', 'sla_policy']);
        $this->assertStringStartsWith('DSP-', $response->json('reference'));
    }

    public function test_client_cannot_view_other_users_dispute(): void
    {
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();

        $case = ComplaintCase::create([
            'reference' => 'DSP-AAAA1234',
            'client_id' => $clientB->id,
            'category' => 'quality',
            'priority' => 'normal',
            'severity' => 'medium',
            'status' => ComplaintCase::STATUS_OPEN,
            'subject' => 'Test',
            'description' => 'Test',
        ]);

        Sanctum::actingAs($clientA);

        $this->getJson('/api/client/disputes/' . $case->id)->assertStatus(403);
    }

    public function test_client_can_message_their_dispute(): void
    {
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);

        $created = $this->postJson('/api/client/disputes', [
            'subject' => 'Problème de communication',
            'description' => 'Description longue suffisante',
            'category' => 'communication',
        ]);

        $created->assertCreated();
        $disputeId = $created->json('id');

        $msg = $this->postJson("/api/client/disputes/{$disputeId}/messages", [
            'body' => 'Voici plus de détails sur le problème.',
        ]);

        $msg->assertCreated();
        $msg->assertJsonStructure(['event_id', 'status']);
    }

    public function test_index_only_returns_own_disputes(): void
    {
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();

        ComplaintCase::create([
            'reference' => 'DSP-OWN0001',
            'client_id' => $clientA->id,
            'category' => 'quality',
            'priority' => 'normal',
            'severity' => 'medium',
            'status' => ComplaintCase::STATUS_OPEN,
            'subject' => 'Owned by A',
            'description' => 'desc',
        ]);

        ComplaintCase::create([
            'reference' => 'DSP-OTHER001',
            'client_id' => $clientB->id,
            'category' => 'quality',
            'priority' => 'normal',
            'severity' => 'medium',
            'status' => ComplaintCase::STATUS_OPEN,
            'subject' => 'Owned by B',
            'description' => 'desc',
        ]);

        Sanctum::actingAs($clientA);

        $response = $this->getJson('/api/client/disputes');
        $response->assertOk();
        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertSame('Owned by A', $items[0]['subject']);
    }
}
