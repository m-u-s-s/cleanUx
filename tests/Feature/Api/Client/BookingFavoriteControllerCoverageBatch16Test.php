<?php

namespace Tests\Feature\Api\Client;

use App\Models\Booking;
use App\Models\BookingFavorite;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingFavoriteControllerCoverageBatch16Test extends TestCase
{
    use RefreshDatabase;

    private function favoriteRow(User $client, array $overrides = []): BookingFavorite
    {
        return BookingFavorite::query()->create(array_merge([
            'client_user_id' => $client->id,
            'label' => 'Favori test',
            'snapshot' => ['estimated_duration_minutes' => 60],
            'use_count' => 0,
        ], $overrides));
    }

    public function test_endpoints_reject_unauthenticated_visitors(): void
    {
        $booking = Booking::factory()->create();

        $this->getJson('/api/client/favorites')->assertUnauthorized();
        $this->postJson("/api/client/bookings/{$booking->id}/favorite")->assertUnauthorized();
        $this->postJson('/api/client/favorites/1/use')->assertUnauthorized();
        $this->deleteJson('/api/client/favorites/1')->assertUnauthorized();
    }

    public function test_index_returns_own_favorites_with_relations_and_null_branches(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create(['name' => 'Marie Provider']);
        $trade = Trade::factory()->create(['name' => 'Nettoyage']);
        $other = User::factory()->client()->create();

        $withRelations = $this->favoriteRow($client, [
            'label' => 'Avec relations',
            'preferred_provider_user_id' => $provider->id,
            'trade_id' => $trade->id,
            'use_count' => 4,
        ]);
        $bare = $this->favoriteRow($client, ['label' => 'Sans relations']);
        $foreign = $this->favoriteRow($other, ['label' => 'Etranger']);

        Sanctum::actingAs($client);

        $data = $this->getJson('/api/client/favorites')
            ->assertOk()
            ->assertJsonStructure(['data' => [[
                'id', 'label', 'preferred_provider', 'trade',
                'snapshot', 'use_count', 'last_used_at', 'created_at',
            ]]])
            ->json('data');

        $ids = collect($data)->pluck('id');
        $this->assertTrue($ids->contains($withRelations->id));
        $this->assertTrue($ids->contains($bare->id));
        $this->assertFalse($ids->contains($foreign->id), 'cross-client leak');

        $rich = collect($data)->firstWhere('id', $withRelations->id);
        $this->assertSame('Marie Provider', $rich['preferred_provider']['name']);
        $this->assertSame('Nettoyage', $rich['trade']['name']);
        $this->assertSame(4, $rich['use_count']);

        $plain = collect($data)->firstWhere('id', $bare->id);
        $this->assertNull($plain['preferred_provider']);
        $this->assertNull($plain['trade']);
    }

    public function test_create_persists_favorite_and_returns_201(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'estimated_duration_minutes' => 90,
        ]);

        Sanctum::actingAs($client);

        $data = $this->postJson("/api/client/bookings/{$booking->id}/favorite", [
            'label' => 'Mon nettoyage hebdo',
        ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'label']])
            ->json('data');

        $this->assertSame('Mon nettoyage hebdo', $data['label']);
        $this->assertDatabaseHas('booking_favorites', [
            'client_user_id' => $client->id,
            'source_booking_id' => $booking->id,
            'label' => 'Mon nettoyage hebdo',
        ]);
    }

    public function test_create_rejects_invalid_label(): void
    {
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id]);

        Sanctum::actingAs($client);

        $this->postJson("/api/client/bookings/{$booking->id}/favorite", [
            'label' => str_repeat('x', 200),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['label']);
    }

    public function test_create_returns_422_for_other_users_booking(): void
    {
        $client = User::factory()->client()->create();
        $owner = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => $owner->id]);

        Sanctum::actingAs($client);

        $this->postJson("/api/client/bookings/{$booking->id}/favorite")
            ->assertStatus(422)
            ->assertJson(['error' => 'validation_failed'])
            ->assertJsonStructure(['error', 'errors']);
    }

    public function test_mark_used_increments_for_owner(): void
    {
        $client = User::factory()->client()->create();
        $favorite = $this->favoriteRow($client, ['use_count' => 2]);

        Sanctum::actingAs($client);

        $this->postJson("/api/client/favorites/{$favorite->id}/use")
            ->assertOk()
            ->assertJson(['data' => ['use_count' => 3]]);

        $this->assertNotNull($favorite->fresh()->last_used_at);
    }

    public function test_mark_used_forbidden_for_other_client(): void
    {
        $owner = User::factory()->client()->create();
        $intruder = User::factory()->client()->create();
        $favorite = $this->favoriteRow($owner);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/client/favorites/{$favorite->id}/use")
            ->assertStatus(403)
            ->assertJson(['error' => 'forbidden']);

        $this->assertSame(0, (int) $favorite->fresh()->use_count);
    }

    public function test_destroy_removes_favorite_for_owner(): void
    {
        $client = User::factory()->client()->create();
        $favorite = $this->favoriteRow($client);

        Sanctum::actingAs($client);

        $this->deleteJson("/api/client/favorites/{$favorite->id}")
            ->assertOk()
            ->assertJson(['data' => ['deleted' => true]]);

        $this->assertDatabaseMissing('booking_favorites', ['id' => $favorite->id]);
    }

    public function test_destroy_forbidden_for_other_client(): void
    {
        $owner = User::factory()->client()->create();
        $intruder = User::factory()->client()->create();
        $favorite = $this->favoriteRow($owner);

        Sanctum::actingAs($intruder);

        $this->deleteJson("/api/client/favorites/{$favorite->id}")
            ->assertStatus(403)
            ->assertJson(['error' => 'forbidden']);

        $this->assertDatabaseHas('booking_favorites', ['id' => $favorite->id]);
    }
}
