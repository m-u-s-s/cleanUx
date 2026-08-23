<?php

namespace Tests\Feature\Api\Client;

use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Feature tests for the multi-trade booking flow API endpoints. */
class TradeBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    private function makeClient(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => 'client'], $attrs));
    }

    private function makeTrade(array $attrs = []): Trade
    {
        return Trade::factory()->create(array_merge(['is_active' => true], $attrs));
    }

    // ─────────────────────────────────────────
    // 1. GET /api/trades — public listing
    // ─────────────────────────────────────────

    public function test_public_trades_listing_returns_active_trades(): void
    {
        $active = $this->makeTrade(['is_active' => true, 'sort_order' => 1]);
        $inactive = $this->makeTrade(['is_active' => false, 'sort_order' => 2]);

        $this->getJson('/api/trades')
            ->assertOk()
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonCount(1, 'data');

        $responseIds = collect($this->getJson('/api/trades')->json('data'))->pluck('id');
        $this->assertNotContains($inactive->id, $responseIds);
    }

    public function test_public_trades_listing_includes_expected_fields(): void
    {
        $this->makeTrade(['name' => 'Nettoyage', 'code' => 'CLN', 'billing_unit' => 'hourly']);

        $this->getJson('/api/trades')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'code', 'icon', 'billing_unit'],
                ],
            ]);
    }

    public function test_public_trades_listing_does_not_require_auth(): void
    {
        $this->makeTrade();

        // No Sanctum::actingAs() — should still return 200
        $this->getJson('/api/trades')->assertOk();
    }

    // ─────────────────────────────────────────
    // 2. GET /api/client/trades/{trade}/form-fields
    // ─────────────────────────────────────────

    public function test_form_fields_requires_auth(): void
    {
        $trade = $this->makeTrade();

        $this->getJson("/api/client/trades/{$trade->id}/form-fields")
            ->assertUnauthorized();
    }

    public function test_form_fields_returns_empty_for_trade_without_schema(): void
    {
        Sanctum::actingAs($this->makeClient());

        $trade = $this->makeTrade(['booking_form_schema' => null]);

        $this->getJson("/api/client/trades/{$trade->id}/form-fields")
            ->assertOk()
            ->assertJsonPath('fields', [])
            ->assertJsonPath('trade.id', $trade->id);
    }

    public function test_form_fields_returns_normalized_schema(): void
    {
        Sanctum::actingAs($this->makeClient());

        $schema = [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'surface_m2',
                    'label' => 'Surface (m²)',
                    'type' => 'number',
                    'required' => true,
                    'min' => 1,
                    'max' => 5000,
                    'unit' => 'm²',
                ],
                [
                    'key' => 'has_pets',
                    'label' => 'Présence animaux',
                    'type' => 'boolean',
                ],
            ],
        ];

        $trade = $this->makeTrade(['booking_form_schema' => $schema, 'billing_unit' => 'hourly']);

        $response = $this->getJson("/api/client/trades/{$trade->id}/form-fields")
            ->assertOk()
            ->assertJsonStructure([
                'trade' => ['id', 'name', 'slug'],
                'fields' => [
                    '*' => ['name', 'type', 'label', 'required'],
                ],
                'billing_unit',
                'requires_site_visit',
            ]);

        $fields = $response->json('fields');
        $this->assertCount(2, $fields);
        $this->assertSame('surface_m2', $fields[0]['name']);
        $this->assertSame('number', $fields[0]['type']);
        $this->assertTrue($fields[0]['required']);
        $this->assertSame('boolean', $fields[1]['type']);
    }

    public function test_form_fields_returns_404_for_nonexistent_trade(): void
    {
        Sanctum::actingAs($this->makeClient());

        $this->getJson('/api/client/trades/99999/form-fields')
            ->assertNotFound();
    }

    public function test_form_fields_returns_billing_unit_and_site_visit(): void
    {
        Sanctum::actingAs($this->makeClient());

        $trade = $this->makeTrade([
            'billing_unit' => 'per_job',
            'requires_site_visit' => true,
        ]);

        $this->getJson("/api/client/trades/{$trade->id}/form-fields")
            ->assertOk()
            ->assertJsonPath('billing_unit', 'per_job')
            ->assertJsonPath('requires_site_visit', true);
    }

    // ─────────────────────────────────────────
    // 3. GET /api/client/trades/{trade}/services
    // ─────────────────────────────────────────

    public function test_services_requires_auth(): void
    {
        $trade = $this->makeTrade();

        $this->getJson("/api/client/trades/{$trade->id}/services")
            ->assertUnauthorized();
    }

    public function test_services_returns_active_services_for_trade(): void
    {
        Sanctum::actingAs($this->makeClient());

        $trade = $this->makeTrade();

        $active = ServiceCatalog::factory()->create(['trade_id' => $trade->id, 'is_active' => true]);
        $inactive = ServiceCatalog::factory()->create(['trade_id' => $trade->id, 'is_active' => false]);

        $response = $this->getJson("/api/client/trades/{$trade->id}/services")
            ->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['id', 'name', 'slug']]]);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($active->id, $ids->toArray());
        $this->assertNotContains($inactive->id, $ids->toArray());
    }

    public function test_services_returns_empty_when_trade_has_no_services(): void
    {
        Sanctum::actingAs($this->makeClient());

        $trade = $this->makeTrade();

        $this->getJson("/api/client/trades/{$trade->id}/services")
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_services_does_not_return_services_from_other_trade(): void
    {
        Sanctum::actingAs($this->makeClient());

        $tradeA = $this->makeTrade();
        $tradeB = $this->makeTrade();

        $serviceA = ServiceCatalog::factory()->create(['trade_id' => $tradeA->id, 'is_active' => true]);
        $serviceB = ServiceCatalog::factory()->create(['trade_id' => $tradeB->id, 'is_active' => true]);

        $ids = collect(
            $this->getJson("/api/client/trades/{$tradeA->id}/services")->json('data')
        )->pluck('id');

        $this->assertContains($serviceA->id, $ids->toArray());
        $this->assertNotContains($serviceB->id, $ids->toArray());
    }

    // ─────────────────────────────────────────
    // 4. POST /api/client/bookings — trade_id + trade_form_answers
    // ─────────────────────────────────────────

    public function test_store_booking_accepts_trade_id_and_form_answers(): void
    {
        Sanctum::actingAs($this->makeClient());

        $trade = $this->makeTrade(['billing_unit' => 'hourly']);
        $service = ServiceCatalog::factory()->create([
            'trade_id' => $trade->id,
            'is_active' => true,
        ]);

        $payload = [
            'trade_id' => $trade->id,
            'service_catalog_id' => $service->id,
            'address' => 'Rue de la Loi 1',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDays(3)->format('Y-m-d'),
            'scheduled_time' => '10:00',
            'trade_form_answers' => [
                'surface_m2' => 80,
                'has_pets' => false,
            ],
        ];

        $this->postJson('/api/client/bookings', $payload)
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'data' => ['id', 'reference', 'status']]);

        $this->assertDatabaseHas('bookings', [
            'service_catalog_id' => $service->id,
            'city' => 'Bruxelles',
        ]);
    }

    public function test_store_booking_persists_trade_form_answers_in_db(): void
    {
        Sanctum::actingAs($this->makeClient());

        $trade = $this->makeTrade();
        $service = ServiceCatalog::factory()->create([
            'trade_id' => $trade->id,
            'is_active' => true,
        ]);

        $answers = ['nb_rooms' => 3, 'floor_type' => 'parquet'];

        $response = $this->postJson('/api/client/bookings', [
            'trade_id' => $trade->id,
            'service_catalog_id' => $service->id,
            'address' => 'Avenue Louise 50',
            'city' => 'Bruxelles',
            'postal_code' => '1050',
            'scheduled_date' => now()->addDays(5)->format('Y-m-d'),
            'scheduled_time' => '14:00',
            'trade_form_answers' => $answers,
        ])->assertCreated();

        $bookingId = $response->json('data.id');

        $booking = Booking::findOrFail($bookingId);
        $this->assertIsArray($booking->trade_form_answers);
        $this->assertSame(3, $booking->trade_form_answers['nb_rooms']);
        $this->assertSame('parquet', $booking->trade_form_answers['floor_type']);
    }

    public function test_store_booking_without_trade_form_answers_still_works(): void
    {
        Sanctum::actingAs($this->makeClient());

        $service = ServiceCatalog::factory()->create(['is_active' => true]);

        $this->postJson('/api/client/bookings', [
            'service_catalog_id' => $service->id,
            'address' => 'Rue Neuve 10',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDays(2)->format('Y-m-d'),
            'scheduled_time' => '09:00',
        ])->assertCreated();
    }

    public function test_store_booking_validates_trade_id_exists(): void
    {
        Sanctum::actingAs($this->makeClient());

        $service = ServiceCatalog::factory()->create(['is_active' => true]);

        $this->postJson('/api/client/bookings', [
            'trade_id' => 99999,
            'service_catalog_id' => $service->id,
            'address' => 'Rue Neuve 10',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDays(2)->format('Y-m-d'),
            'scheduled_time' => '09:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['trade_id']);
    }
}
