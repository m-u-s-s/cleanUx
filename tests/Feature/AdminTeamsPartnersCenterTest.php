<?php

namespace Tests\Feature;

use App\Livewire\Admin\GestionEquipesPartenaires;
use App\Models\Country;
use App\Models\FieldTeam;
use App\Models\ServicePartner;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTeamsPartnersCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_permission_can_access_teams_and_partners_center(): void
    {
        $admin = User::factory()->admin()->create([
            'permissions' => ['manage-entreprises'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.teams.partners'))
            ->assertOk()
            ->assertSee('Équipes terrain & partenaires');
    }

    public function test_admin_can_create_team_and_partner_foundations(): void
    {
        $country = Country::factory()->create();
        $zone = ServiceZone::factory()->create(['country_id' => $country->id]);
        $lead = User::factory()->employe()->create();
        $admin = User::factory()->admin()->create([
            'permissions' => ['manage-entreprises'],
        ]);

        $this->actingAs($admin);

        Livewire::test(GestionEquipesPartenaires::class)
            ->set('partnerForm.name', 'Spark Partner')
            ->set('partnerForm.country_id', $country->id)
            ->call('savePartner')
            ->assertDispatched('toast')
            ->set('teamForm.name', 'Crew Bruxelles Nord')
            ->set('teamForm.country_id', $country->id)
            ->set('teamForm.service_zone_id', $zone->id)
            ->set('teamForm.team_lead_user_id', $lead->id)
            ->set('teamForm.status', 'active')
            ->set('teamForm.is_internal', true)
            ->call('saveTeam')
            ->assertDispatched('toast');

        $team = FieldTeam::query()->where('name', 'Crew Bruxelles Nord')->first();
        $partner = ServicePartner::query()->where('name', 'Spark Partner')->first();

        $this->assertNotNull($team);
        $this->assertNotNull($partner);
        $this->assertDatabaseHas('field_team_members', [
            'field_team_id' => $team->id,
            'user_id' => $lead->id,
            'is_team_lead' => true,
        ]);
    }
}
