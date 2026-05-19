<?php

namespace Tests\Feature\Gdpr;

use App\Models\GdprDataRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GdprApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_request_export_creates_and_fulfills_request(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/client/gdpr/requests/export');

        $response->assertCreated();
        $response->assertJsonStructure(['request_id', 'reference', 'status', 'expires_at']);

        $this->assertSame(1, GdprDataRequest::query()
            ->where('user_id', $user->id)
            ->ofType(GdprDataRequest::TYPE_EXPORT)
            ->count());
    }

    public function test_request_erasure_requires_confirm(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/client/gdpr/requests/erasure', [
            'confirm' => false,
        ]);

        $response->assertStatus(422);
    }

    public function test_request_erasure_with_confirm_schedules_it(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/client/gdpr/requests/erasure', [
            'confirm' => true,
            'reason' => 'Je ne veux plus utiliser le service',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('status', GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD);

        $this->assertSame(1, GdprDataRequest::query()
            ->where('user_id', $user->id)
            ->ofType(GdprDataRequest::TYPE_ERASURE)
            ->count());
    }

    public function test_cannot_request_erasure_twice_simultaneously(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/client/gdpr/requests/erasure', ['confirm' => true]);

        $response = $this->postJson('/api/client/gdpr/requests/erasure', ['confirm' => true]);
        $response->assertStatus(409);
    }

    public function test_index_returns_user_requests_only(): void
    {
        $userA = User::factory()->client()->create();
        $userB = User::factory()->client()->create();

        GdprDataRequest::create([
            'user_id' => $userA->id,
            'type' => GdprDataRequest::TYPE_EXPORT,
            'status' => GdprDataRequest::STATUS_FULFILLED,
            'reference' => 'GDPR-USERAAAA',
            'requested_at' => now(),
        ]);
        GdprDataRequest::create([
            'user_id' => $userB->id,
            'type' => GdprDataRequest::TYPE_EXPORT,
            'status' => GdprDataRequest::STATUS_FULFILLED,
            'reference' => 'GDPR-USERBBBB',
            'requested_at' => now(),
        ]);

        Sanctum::actingAs($userA);
        $response = $this->getJson('/api/client/gdpr/requests');

        $response->assertOk();
        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertSame('GDPR-USERAAAA', $items[0]['reference']);
    }

    public function test_cancel_erasure_endpoint(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/client/gdpr/requests/erasure', ['confirm' => true]);
        $requestId = $created->json('request_id');

        $response = $this->postJson("/api/client/gdpr/requests/{$requestId}/cancel");
        $response->assertOk();
        $response->assertJsonPath('status', GdprDataRequest::STATUS_CANCELLED);
    }

    public function test_cancel_forbidden_cross_user(): void
    {
        $userA = User::factory()->client()->create();
        $userB = User::factory()->client()->create();
        Sanctum::actingAs($userB);
        $req = GdprDataRequest::create([
            'user_id' => $userA->id,
            'type' => GdprDataRequest::TYPE_ERASURE,
            'status' => GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
            'reference' => 'GDPR-USERXXX1',
            'requested_at' => now(),
            'grace_period_ends_at' => now()->addDays(30),
        ]);

        $this->postJson("/api/client/gdpr/requests/{$req->id}/cancel")
            ->assertStatus(403);
    }
}
