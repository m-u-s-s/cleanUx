<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\Promotions\PromoCampaignsCenter;
use App\Models\PromoCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromoCampaignsCenterCoverageBatch9Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_render_lists_campaigns(): void
    {
        $campaign = PromoCampaign::factory()->create(['name' => 'Summer Splash']);

        Livewire::actingAs($this->admin)
            ->test(PromoCampaignsCenter::class)
            ->assertOk()
            ->assertViewHas('campaigns')
            ->assertSee('Summer Splash')
            ->assertSet('status', PromoCampaign::STATUS_DRAFT);
    }

    public function test_updated_name_auto_generates_slug_when_creating(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PromoCampaignsCenter::class)
            ->set('name', 'My Great Campaign')
            ->assertSet('slug', 'my-great-campaign');
    }

    public function test_updated_name_does_not_override_existing_slug(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PromoCampaignsCenter::class)
            ->set('slug', 'kept-slug')
            ->set('name', 'Another Name')
            ->assertSet('slug', 'kept-slug');
    }

    public function test_save_creates_campaign(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PromoCampaignsCenter::class)
            ->set('name', 'Black Friday')
            ->set('slug', 'black-friday')
            ->set('description', 'Big sale')
            ->set('status', PromoCampaign::STATUS_ACTIVE)
            ->set('starts_at', '2026-06-01T10:00')
            ->set('ends_at', '2026-06-30T10:00')
            ->set('budget_cap', 1500.50)
            ->set('target_audience', 'all')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('editingId', null)
            ->assertSet('name', '');

        $this->assertDatabaseHas('promo_campaigns', [
            'slug' => 'black-friday',
            'name' => 'Black Friday',
            'status' => PromoCampaign::STATUS_ACTIVE,
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'promo_campaign.created',
        ]);
    }

    public function test_save_validates_required_and_formats(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PromoCampaignsCenter::class)
            ->set('name', '')
            ->set('slug', 'Invalid Slug!')
            ->set('status', 'bogus')
            ->set('budget_cap', -5)
            ->call('save')
            ->assertHasErrors([
                'name' => 'required',
                'slug' => 'regex',
                'status' => 'in',
                'budget_cap' => 'min',
            ]);
    }

    public function test_save_rejects_duplicate_slug(): void
    {
        PromoCampaign::factory()->create(['slug' => 'taken-slug']);

        Livewire::actingAs($this->admin)
            ->test(PromoCampaignsCenter::class)
            ->set('name', 'Dup')
            ->set('slug', 'taken-slug')
            ->call('save')
            ->assertHasErrors(['slug' => 'unique']);
    }

    public function test_edit_loads_campaign_then_save_updates(): void
    {
        $campaign = PromoCampaign::factory()->create([
            'name' => 'Original',
            'slug' => 'original-slug',
            'description' => 'Desc',
            'status' => PromoCampaign::STATUS_DRAFT,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'budget_cap' => 200,
            'target_audience' => 'clients',
        ]);

        Livewire::actingAs($this->admin)
            ->test(PromoCampaignsCenter::class)
            ->call('edit', $campaign->id)
            ->assertSet('editingId', $campaign->id)
            ->assertSet('name', 'Original')
            ->assertSet('slug', 'original-slug')
            ->assertSet('target_audience', 'clients')
            ->set('name', 'Renamed')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('editingId', null);

        $this->assertDatabaseHas('promo_campaigns', [
            'id' => $campaign->id,
            'name' => 'Renamed',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'promo_campaign.updated',
        ]);
    }

    public function test_edit_allows_keeping_same_slug_on_update(): void
    {
        $campaign = PromoCampaign::factory()->create(['slug' => 'self-slug']);

        Livewire::actingAs($this->admin)
            ->test(PromoCampaignsCenter::class)
            ->call('edit', $campaign->id)
            ->set('name', 'Keeps Slug')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_edit_loads_null_optional_fields(): void
    {
        $campaign = PromoCampaign::factory()->create([
            'description' => null,
            'starts_at' => null,
            'ends_at' => null,
            'budget_cap' => null,
            'target_audience' => null,
        ]);

        Livewire::actingAs($this->admin)
            ->test(PromoCampaignsCenter::class)
            ->call('edit', $campaign->id)
            ->assertSet('description', '')
            ->assertSet('starts_at', null)
            ->assertSet('budget_cap', null)
            ->assertSet('target_audience', '');
    }

    public function test_pause_sets_status_paused(): void
    {
        $campaign = PromoCampaign::factory()->create(['status' => PromoCampaign::STATUS_ACTIVE]);

        Livewire::actingAs($this->admin)
            ->test(PromoCampaignsCenter::class)
            ->call('pause', $campaign->id)
            ->assertDispatched('toast');

        $this->assertSame(PromoCampaign::STATUS_PAUSED, $campaign->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'promo_campaign.paused']);
    }

    public function test_activate_sets_status_active(): void
    {
        $campaign = PromoCampaign::factory()->draft()->create();

        Livewire::actingAs($this->admin)
            ->test(PromoCampaignsCenter::class)
            ->call('activate', $campaign->id)
            ->assertDispatched('toast');

        $this->assertSame(PromoCampaign::STATUS_ACTIVE, $campaign->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'promo_campaign.activated']);
    }

    public function test_reset_form_clears_fields(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PromoCampaignsCenter::class)
            ->set('editingId', 7)
            ->set('name', 'Something')
            ->set('status', PromoCampaign::STATUS_ACTIVE)
            ->call('resetForm')
            ->assertSet('editingId', null)
            ->assertSet('name', '')
            ->assertSet('status', PromoCampaign::STATUS_DRAFT);
    }
}
