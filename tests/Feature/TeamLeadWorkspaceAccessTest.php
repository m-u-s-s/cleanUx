<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\FieldTeam;
use App\Models\FieldTeamMember;
use App\Models\Mission;
use App\Models\MissionTeamAssignment;
use App\Models\RendezVous;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamLeadWorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_access_team_workspace_and_see_team_assignment(): void
    {
        $country = Country::factory()->create();
        $zone = ServiceZone::factory()->create(['country_id' => $country->id]);
        $lead = User::factory()->employe()->create();
        $client = User::factory()->client()->create();
        $rdv = RendezVous::factory()->create([
            'client_id' => $client->id,
            'service_zone_id' => $zone->id,
            'status' => 'confirme',
        ]);

        $team = FieldTeam::query()->create([
            'country_id' => $country->id,
            'service_zone_id' => $zone->id,
            'team_lead_user_id' => $lead->id,
            'name' => 'Lead Crew',
            'slug' => 'lead-crew',
            'status' => 'active',
            'is_internal' => true,
        ]);

        FieldTeamMember::query()->create([
            'field_team_id' => $team->id,
            'user_id' => $lead->id,
            'role_on_team' => 'team_lead',
            'is_team_lead' => true,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $mission = Mission::query()->firstOrCreate([
            'rendez_vous_id' => $rdv->id,
        ], [
            'service_zone_id' => $zone->id,
            'lead_employee_id' => $lead->id,
            'status' => 'assigned',
            'mission_type' => 'standard',
            'planned_start_at' => now()->addHour(),
            'planned_end_at' => now()->addHours(3),
        ]);

        MissionTeamAssignment::query()->create([
            'mission_id' => $mission->id,
            'field_team_id' => $team->id,
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $this->actingAs($lead)
            ->get(route('employe.team'))
            ->assertOk()
            ->assertSee('Lead Crew')
            ->assertSee('Missions d’équipe actives');
    }
}
