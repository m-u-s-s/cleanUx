<?php

namespace Tests\Feature;

use App\Livewire\Admin\GestionEquipesPartenaires;
use App\Models\Country;
use App\Models\FieldTeam;
use App\Models\OrganizationAccount;
use App\Models\ServiceCatalog;
use App\Models\ServicePartner;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTeamsPartnersCenterCoverageBatch6Test extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->admin()->create([
            'permissions' => ['manage-entreprises'],
        ]);
    }

    public function test_select_team_populates_form_and_selected_team_property(): void
    {
        $country = Country::factory()->create();
        $zone = ServiceZone::factory()->create(['country_id' => $country->id]);
        $team = FieldTeam::factory()->create([
            'country_id' => $country->id,
            'service_zone_id' => $zone->id,
            'name' => 'Crew Centre',
            'status' => 'pilot',
            'is_internal' => false,
            'max_concurrent_missions' => 7,
            'notes' => 'Note interne',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(GestionEquipesPartenaires::class)
            ->call('selectTeam', $team->id)
            ->assertSet('selectedTeamId', $team->id)
            ->assertSet('teamForm.name', 'Crew Centre')
            ->assertSet('teamForm.status', 'pilot')
            ->assertSet('teamForm.is_internal', false)
            ->assertSet('teamForm.max_concurrent_missions', 7);
    }

    public function test_new_team_resets_selection_and_form(): void
    {
        $country = Country::factory()->create();
        $team = FieldTeam::factory()->create(['country_id' => $country->id]);

        $this->actingAs($this->admin());

        Livewire::test(GestionEquipesPartenaires::class)
            ->call('selectTeam', $team->id)
            ->assertSet('selectedTeamId', $team->id)
            ->call('newTeam')
            ->assertSet('selectedTeamId', null)
            ->assertSet('teamForm.name', '')
            ->assertSet('teamForm.status', 'active');
    }

    public function test_save_team_updates_existing_team(): void
    {
        $country = Country::factory()->create();
        $account = OrganizationAccount::factory()->create(['country_id' => $country->id]);
        $team = FieldTeam::factory()->create([
            'country_id' => $country->id,
            'name' => 'Ancien Nom',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(GestionEquipesPartenaires::class)
            ->call('selectTeam', $team->id)
            ->set('teamForm.name', 'Nouveau Nom')
            ->set('teamForm.organization_account_id', $account->id)
            ->set('teamForm.status', 'inactive')
            ->call('saveTeam')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('field_teams', [
            'id' => $team->id,
            'name' => 'Nouveau Nom',
            'status' => 'inactive',
            'organization_account_id' => $account->id,
        ]);
    }

    public function test_save_team_defaults_max_concurrent_missions(): void
    {
        $country = Country::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(GestionEquipesPartenaires::class)
            ->set('teamForm.name', 'Team Sans Capacite')
            ->set('teamForm.country_id', $country->id)
            ->set('teamForm.status', 'active')
            ->set('teamForm.max_concurrent_missions', null)
            ->call('saveTeam')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('field_teams', [
            'name' => 'Team Sans Capacite',
            'max_concurrent_missions' => 3,
        ]);
    }

    public function test_add_member_as_team_lead_updates_team_lead(): void
    {
        $country = Country::factory()->create();
        $team = FieldTeam::factory()->create(['country_id' => $country->id]);
        $employe = User::factory()->employe()->create();

        $this->actingAs($this->admin());

        Livewire::test(GestionEquipesPartenaires::class)
            ->call('selectTeam', $team->id)
            ->set('memberForm.user_id', $employe->id)
            ->set('memberForm.role_on_team', 'team_lead')
            ->set('memberForm.is_team_lead', true)
            ->call('addMember')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('memberForm.user_id', null);

        $this->assertDatabaseHas('field_team_members', [
            'field_team_id' => $team->id,
            'user_id' => $employe->id,
            'is_team_lead' => true,
        ]);
        $this->assertDatabaseHas('field_teams', [
            'id' => $team->id,
            'team_lead_user_id' => $employe->id,
        ]);
    }

    public function test_add_member_requires_user(): void
    {
        $country = Country::factory()->create();
        $team = FieldTeam::factory()->create(['country_id' => $country->id]);

        $this->actingAs($this->admin());

        Livewire::test(GestionEquipesPartenaires::class)
            ->call('selectTeam', $team->id)
            ->set('memberForm.user_id', null)
            ->call('addMember')
            ->assertHasErrors(['memberForm.user_id' => 'required']);
    }

    public function test_select_partner_populates_form_and_new_partner_resets(): void
    {
        $country = Country::factory()->create();
        $partner = ServicePartner::factory()->create([
            'country_id' => $country->id,
            'name' => 'Partner X',
            'status' => 'pilot',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(GestionEquipesPartenaires::class)
            ->call('selectPartner', $partner->id)
            ->assertSet('selectedPartnerId', $partner->id)
            ->assertSet('partnerForm.name', 'Partner X')
            ->assertSet('partnerForm.status', 'pilot')
            ->call('newPartner')
            ->assertSet('selectedPartnerId', null)
            ->assertSet('partnerForm.name', '');
    }

    public function test_save_partner_updates_existing_partner(): void
    {
        $country = Country::factory()->create();
        $partner = ServicePartner::factory()->create([
            'country_id' => $country->id,
            'name' => 'Old Partner',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(GestionEquipesPartenaires::class)
            ->call('selectPartner', $partner->id)
            ->set('partnerForm.name', 'Renamed Partner')
            ->set('partnerForm.status', 'inactive')
            ->set('partnerForm.quality_score', 88)
            ->call('savePartner')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('service_partners', [
            'id' => $partner->id,
            'name' => 'Renamed Partner',
            'status' => 'inactive',
        ]);
    }

    public function test_add_coverage_creates_partner_zone_coverage(): void
    {
        $country = Country::factory()->create();
        $partner = ServicePartner::factory()->create(['country_id' => $country->id]);
        $zone = ServiceZone::factory()->create(['country_id' => $country->id]);
        $service = ServiceCatalog::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(GestionEquipesPartenaires::class)
            ->call('selectPartner', $partner->id)
            ->set('coverageForm.service_zone_id', $zone->id)
            ->set('coverageForm.service_catalog_id', $service->id)
            ->set('coverageForm.priority', 5)
            ->set('coverageForm.max_daily_capacity', 20)
            ->set('coverageForm.sla_response_hours', 12)
            ->call('addCoverage')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('coverageForm.priority', 1);

        $this->assertDatabaseHas('partner_zone_coverages', [
            'service_partner_id' => $partner->id,
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $service->id,
            'priority' => 5,
            'coverage_status' => 'active',
        ]);
    }

    public function test_render_exposes_reference_collections(): void
    {
        $country = Country::factory()->create();
        OrganizationAccount::factory()->create(['country_id' => $country->id]);
        ServiceCatalog::factory()->create();
        ServiceZone::factory()->create(['country_id' => $country->id]);
        User::factory()->employe()->create();

        $this->actingAs($this->admin());

        Livewire::test(GestionEquipesPartenaires::class)
            ->assertOk()
            ->assertViewHas('accounts')
            ->assertViewHas('employees')
            ->assertViewHas('services')
            ->assertViewHas('zones')
            ->assertViewHas('countries');
    }
}
