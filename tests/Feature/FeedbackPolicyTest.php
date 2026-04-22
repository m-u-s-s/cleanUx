<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_peut_voir_son_feedback(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $employe = User::factory()->create(['role' => 'employe']);

        $rdv = RendezVous::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $employe->id,
        ]);

        $feedback = Feedback::factory()->create([
            'client_id' => $client->id,
            'rendez_vous_id' => $rdv->id,
        ]);

        $this->actingAs($client);

        $this->assertTrue($client->can('view', $feedback));
    }

    public function test_autre_client_ne_peut_pas_voir_le_feedback(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $autreClient = User::factory()->create(['role' => 'client']);
        $employe = User::factory()->create(['role' => 'employe']);

        $rdv = RendezVous::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $employe->id,
        ]);

        $feedback = Feedback::factory()->create([
            'client_id' => $client->id,
            'rendez_vous_id' => $rdv->id,
        ]);

        $this->actingAs($autreClient);

        $this->assertFalse($autreClient->can('view', $feedback));
    }
}