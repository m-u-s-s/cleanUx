<?php

namespace Tests\Feature;

use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RendezVousPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_peut_modifier_son_rendez_vous_en_attente(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $employe = User::factory()->create(['role' => 'employe']);

        $rdv = RendezVous::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $employe->id,
            'status' => 'en_attente',
        ]);

        $this->actingAs($client);

        $this->assertTrue($client->can('update', $rdv));
    }

    public function test_client_ne_peut_pas_modifier_un_rendez_vous_termine(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $employe = User::factory()->create(['role' => 'employe']);

        $rdv = RendezVous::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $employe->id,
            'status' => 'termine',
        ]);

        $this->actingAs($client);

        $this->assertFalse($client->can('update', $rdv));
    }

    public function test_employe_ne_peut_modifier_que_ses_missions(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $employe1 = User::factory()->create(['role' => 'employe']);
        $employe2 = User::factory()->create(['role' => 'employe']);

        $rdv = RendezVous::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $employe1->id,
            'status' => 'confirme',
        ]);

        $this->actingAs($employe2);

        $this->assertFalse($employe2->can('update', $rdv));
    }
}