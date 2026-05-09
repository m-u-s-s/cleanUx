<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientReschedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_reschedule_own_editable_rendez_vous(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'is_active' => true,
        ]);

        $rdv = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => BookingStatus::EN_ATTENTE,
            'date' => now()->addDays(3)->toDateString(),
            'heure' => '10:00',
        ]);

        $this->actingAs($client);

        $this->assertTrue($rdv->fresh()->canStillBeEditedByClient());
    }

    public function test_client_cannot_reschedule_finished_rendez_vous(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'is_active' => true,
        ]);

        $rdv = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => BookingStatus::TERMINE,
        ]);

        $this->actingAs($client);

        $this->assertFalse($rdv->fresh()->canStillBeEditedByClient());
    }

    public function test_client_cannot_edit_another_client_rendez_vous(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'is_active' => true,
        ]);

        $otherClient = User::factory()->create([
            'role' => 'client',
            'is_active' => true,
        ]);

        $rdv = Booking::factory()->create([
            'client_id' => $otherClient->id,
            'status' => BookingStatus::EN_ATTENTE,
        ]);

        $this->actingAs($client);

        $this->assertFalse($client->can('update', $rdv));
    }
}