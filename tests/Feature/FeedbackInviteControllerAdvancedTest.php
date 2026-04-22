<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackInviteControllerAdvancedTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_open_feedback_form_for_own_rendez_vous(): void
    {
        $client = User::factory()->client()->create();
        $rdv = RendezVous::factory()->termine()->create(['client_id' => $client->id]);

        $this->actingAs($client)
            ->get(route('feedback.create', $rdv))
            ->assertOk();
    }

    public function test_other_client_cannot_open_feedback_form(): void
    {
        $owner = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $rdv = RendezVous::factory()->termine()->create(['client_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('feedback.create', $rdv))
            ->assertForbidden();
    }

    public function test_client_can_submit_feedback_once(): void
    {
        $client = User::factory()->client()->create();
        $rdv = RendezVous::factory()->termine()->create(['client_id' => $client->id]);

        $this->actingAs($client)
            ->post(route('feedback.store', $rdv), [
                'note' => 5,
                'commentaire' => 'Très bon service',
            ])
            ->assertRedirect(route('client.dashboard'));

        $this->assertDatabaseHas('feedback', [
            'client_id' => $client->id,
            'rendez_vous_id' => $rdv->id,
            'note' => 5,
        ]);
    }

    public function test_duplicate_feedback_is_blocked(): void
    {
        $client = User::factory()->client()->create();
        $rdv = RendezVous::factory()->termine()->create(['client_id' => $client->id]);
        Feedback::factory()->forRendezVous($rdv)->create();

        $this->actingAs($client)
            ->from(route('feedback.create', $rdv))
            ->post(route('feedback.store', $rdv), [
                'note' => 4,
                'commentaire' => 'Deuxième envoi',
            ])
            ->assertRedirect(route('feedback.create', $rdv));

        $this->assertCount(1, Feedback::where('rendez_vous_id', $rdv->id)->get());
    }
}
