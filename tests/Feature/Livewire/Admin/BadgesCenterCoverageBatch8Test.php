<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\Badges\BadgesCenter;
use App\Models\ProviderBadge;
use App\Models\ProviderBadgeAward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BadgesCenterCoverageBatch8Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->provider = User::factory()->employe()->create();
    }

    private function makeBadge(array $overrides = []): ProviderBadge
    {
        return ProviderBadge::create(array_merge([
            'code' => 'code_'.substr(uniqid(), -8),
            'name' => 'Badge '.substr(uniqid(), -4),
            'description' => 'A description',
            'icon' => '🏆',
            'tier' => 'bronze',
            'criterion_type' => 'missions_count',
            'threshold' => 10,
            'is_active' => true,
            'metadata' => [],
        ], $overrides));
    }

    public function test_mount_renders_with_badges_awards_and_stats(): void
    {
        $badge = $this->makeBadge();
        ProviderBadgeAward::create([
            'provider_user_id' => $this->provider->id,
            'badge_id' => $badge->id,
            'value_at_award' => 25,
            'awarded_at' => now(),
            'metadata' => [],
        ]);

        Livewire::actingAs($this->admin)
            ->test(BadgesCenter::class)
            ->assertOk()
            ->assertViewHas('badges')
            ->assertViewHas('awards')
            ->assertViewHas('stats');
    }

    public function test_set_tab_changes_tab(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BadgesCenter::class)
            ->call('setTab', 'awards')
            ->assertSet('tab', 'awards');
    }

    public function test_open_create_initializes_form(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BadgesCenter::class)
            ->call('openCreate')
            ->assertSet('showForm', true)
            ->assertSet('editBadgeId', null)
            ->assertSet('form_tier', 'bronze')
            ->assertSet('form_criterion_type', 'missions_count')
            ->assertSet('form_threshold', 10)
            ->assertSet('form_is_active', true);
    }

    public function test_open_edit_loads_badge_into_form(): void
    {
        $badge = $this->makeBadge([
            'code' => 'edit_me',
            'name' => 'Editable Badge',
            'tier' => 'gold',
            'criterion_type' => 'rating_avg',
            'threshold' => 42,
            'is_active' => false,
        ]);

        Livewire::actingAs($this->admin)
            ->test(BadgesCenter::class)
            ->call('openEdit', $badge->id)
            ->assertSet('showForm', true)
            ->assertSet('editBadgeId', $badge->id)
            ->assertSet('form_code', 'edit_me')
            ->assertSet('form_name', 'Editable Badge')
            ->assertSet('form_tier', 'gold')
            ->assertSet('form_criterion_type', 'rating_avg')
            ->assertSet('form_threshold', 42)
            ->assertSet('form_is_active', false);
    }

    public function test_close_form_resets_state(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BadgesCenter::class)
            ->set('showForm', true)
            ->set('editBadgeId', 5)
            ->call('closeForm')
            ->assertSet('showForm', false)
            ->assertSet('editBadgeId', null);
    }

    public function test_save_creates_new_badge(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BadgesCenter::class)
            ->call('openCreate')
            ->set('form_code', 'new_badge_code')
            ->set('form_name', 'Brand New Badge')
            ->set('form_description', 'A nice description')
            ->set('form_tier', 'silver')
            ->set('form_criterion_type', 'tips_received')
            ->set('form_threshold', 20)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('provider_badges', [
            'code' => 'new_badge_code',
            'name' => 'Brand New Badge',
            'tier' => 'silver',
            'criterion_type' => 'tips_received',
            'threshold' => 20,
        ]);
    }

    public function test_save_updates_existing_badge(): void
    {
        $badge = $this->makeBadge([
            'name' => 'Old Name',
            'threshold' => 5,
        ]);

        Livewire::actingAs($this->admin)
            ->test(BadgesCenter::class)
            ->call('openEdit', $badge->id)
            ->set('form_name', 'Updated Name')
            ->set('form_threshold', 99)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('provider_badges', [
            'id' => $badge->id,
            'name' => 'Updated Name',
            'threshold' => 99,
        ]);
    }

    public function test_save_validates_required_and_enums(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BadgesCenter::class)
            ->call('openCreate')
            ->set('form_code', '')
            ->set('form_name', '')
            ->set('form_tier', 'diamond')
            ->set('form_criterion_type', 'invalid_type')
            ->set('form_threshold', 0)
            ->call('save')
            ->assertHasErrors([
                'form_code' => 'required',
                'form_name' => 'required',
                'form_tier' => 'in',
                'form_criterion_type' => 'in',
                'form_threshold' => 'min',
            ]);
    }

    public function test_toggle_active_flips_state(): void
    {
        $badge = $this->makeBadge(['is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test(BadgesCenter::class)
            ->call('toggleActive', $badge->id)
            ->assertDispatched('toast');

        $this->assertFalse((bool) $badge->fresh()->is_active);
    }

    public function test_render_applies_search_filter(): void
    {
        $this->makeBadge(['code' => 'findable_code', 'name' => 'Findable Badge']);
        $this->makeBadge(['code' => 'other_code', 'name' => 'Other Badge']);

        Livewire::actingAs($this->admin)
            ->test(BadgesCenter::class)
            ->set('search', 'findable')
            ->assertOk()
            ->assertSee('Findable Badge')
            ->assertDontSee('Other Badge');
    }
}
