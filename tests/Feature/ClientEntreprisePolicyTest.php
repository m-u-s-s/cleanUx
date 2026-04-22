<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientEntreprisePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_entreprise_user_can_create_rendez_vous(): void
    {
        $entreprise = User::factory()->entreprise()->create();

        $this->assertTrue($entreprise->can('create', RendezVous::class));
    }

    public function test_entreprise_user_can_create_and_manage_own_feedback(): void
    {
        $entreprise = User::factory()->entreprise()->create();
        $employee = User::factory()->employe()->create();

        $rdv = RendezVous::factory()->create([
            'client_id' => $entreprise->id,
            'employe_id' => $employee->id,
        ]);

        $feedback = Feedback::factory()->create([
            'client_id' => $entreprise->id,
            'rendez_vous_id' => $rdv->id,
        ]);

        $this->assertTrue($entreprise->can('create', Feedback::class));
        $this->assertTrue($entreprise->can('view', $feedback));
        $this->assertTrue($entreprise->can('update', $feedback));
        $this->assertTrue($entreprise->can('delete', $feedback));
    }
}
