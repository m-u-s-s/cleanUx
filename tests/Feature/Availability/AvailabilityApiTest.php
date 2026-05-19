<?php

namespace Tests\Feature\Availability;

use App\Models\AvailabilityException;
use App\Models\AvailabilitySlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProvider(): User
    {
        return User::factory()->employe()->create();
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/provider/availability')->assertStatus(401);
    }

    public function test_index_returns_only_authenticated_provider_data(): void
    {
        $alice = $this->makeProvider();
        $bob = $this->makeProvider();

        AvailabilitySlot::create([
            'provider_user_id' => $alice->id,
            'weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00',
        ]);
        AvailabilitySlot::create([
            'provider_user_id' => $bob->id,
            'weekday' => 2, 'start_time' => '08:00:00', 'end_time' => '16:00:00',
        ]);

        Sanctum::actingAs($alice);
        $response = $this->getJson('/api/provider/availability');

        $response->assertOk();
        $this->assertCount(1, $response->json('slots'));
        $this->assertSame(1, $response->json('slots.0.weekday'));
    }

    public function test_store_slot_validates_required_fields(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/availability/slots', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weekday', 'start_time', 'end_time']);
    }

    public function test_store_slot_rejects_end_before_start(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/availability/slots', [
            'weekday' => 1,
            'start_time' => '17:00',
            'end_time' => '09:00',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['end_time']);
    }

    public function test_store_slot_creates_record(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $response = $this->postJson('/api/provider/availability/slots', [
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, AvailabilitySlot::query()->forProvider($provider->id)->count());
    }

    public function test_update_slot_rejects_other_provider(): void
    {
        $alice = $this->makeProvider();
        $bob = $this->makeProvider();

        $slot = AvailabilitySlot::create([
            'provider_user_id' => $alice->id,
            'weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00',
        ]);

        Sanctum::actingAs($bob);

        $this->putJson("/api/provider/availability/slots/{$slot->id}", [
            'start_time' => '10:00',
        ])->assertStatus(403);
    }

    public function test_destroy_slot_removes_record(): void
    {
        $provider = $this->makeProvider();
        $slot = AvailabilitySlot::create([
            'provider_user_id' => $provider->id,
            'weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00',
        ]);

        Sanctum::actingAs($provider);
        $this->deleteJson("/api/provider/availability/slots/{$slot->id}")->assertOk();

        $this->assertSame(0, AvailabilitySlot::count());
    }

    public function test_store_exception_requires_times_for_partial(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $response = $this->postJson('/api/provider/availability/exceptions', [
            'date' => now()->addDay()->format('Y-m-d'),
            'exception_type' => 'partial',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_exception_closed_does_not_require_times(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $response = $this->postJson('/api/provider/availability/exceptions', [
            'date' => now()->addDay()->format('Y-m-d'),
            'exception_type' => 'closed',
            'reason' => 'Vacances',
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, AvailabilityException::count());
    }

    public function test_windows_endpoint_returns_iso_intervals(): void
    {
        $provider = $this->makeProvider();
        $monday = \Carbon\CarbonImmutable::now()->next(\Carbon\CarbonImmutable::MONDAY)->startOfDay();

        AvailabilitySlot::create([
            'provider_user_id' => $provider->id,
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        Sanctum::actingAs($provider);

        $response = $this->getJson('/api/provider/availability/windows?' . http_build_query([
            'from' => $monday->format('Y-m-d'),
            'to' => $monday->copy()->addDay()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('start', $data[0]);
        $this->assertArrayHasKey('end', $data[0]);
    }

    public function test_ical_endpoint_returns_text_calendar(): void
    {
        $provider = $this->makeProvider();

        AvailabilitySlot::create([
            'provider_user_id' => $provider->id,
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        Sanctum::actingAs($provider);

        $response = $this->getJson('/api/provider/availability/ical');

        $response->assertOk();
        $this->assertStringContainsString('text/calendar', (string) $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('END:VCALENDAR', $content);
    }
}
