<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\FieldTeam;
use App\Models\FieldTeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeAdvancedCentersRouteIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employe_can_access_team_and_coordination_pages(): void
    {
        $employee = User::factory()->employe()->create(['is_active' => true]);

        $this->actingAs($employee);

        $this->get(route('employe.team'))->assertOk();
        $this->get(route('employe.coordination'))->assertOk();
    }

    public function test_team_lead_can_access_team_lead_operations_page(): void
    {
        $country = Country::factory()->create();
        $lead = User::factory()->employe()->create(['is_active' => true]);
        $team = FieldTeam::query()->create([
            'country_id' => $country->id,
            'name' => 'Lead Ops',
            'slug' => 'lead-ops',
            'status' => 'active',
            'is_internal' => true,
            'team_lead_user_id' => $lead->id,
        ]);
        FieldTeamMember::query()->create([
            'field_team_id' => $team->id,
            'user_id' => $lead->id,
            'role_on_team' => 'team_lead',
            'is_team_lead' => true,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->actingAs($lead)
            ->get(route('employe.teamlead.operations'))->assertOk();
    }

    public function test_regular_employee_cannot_access_team_lead_operations_page(): void
    {
        $employee = User::factory()->employe()->create(['is_active' => true]);

        $this->actingAs($employee)
            ->get(route('employe.teamlead.operations'))->assertForbidden();
    }
}
