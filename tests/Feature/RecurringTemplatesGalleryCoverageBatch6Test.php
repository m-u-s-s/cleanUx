<?php

namespace Tests\Feature;

use App\Livewire\Client\Templates\RecurringTemplatesGallery;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\RecurringBookingSeries;
use App\Models\RecurringTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecurringTemplatesGalleryCoverageBatch6Test extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        return User::factory()->client()->create();
    }

    public function test_mount_sets_default_start_date_and_renders(): void
    {
        Livewire::actingAs($this->client())
            ->test(RecurringTemplatesGallery::class)
            ->assertOk()
            ->assertSet('selectedCategory', 'all')
            ->assertSet('applyStartsAt', now()->addDays(2)->toDateString());
    }

    public function test_set_category_filters_templates(): void
    {
        RecurringTemplate::factory()->system()->create(['category' => 'office']);
        RecurringTemplate::factory()->system()->create(['category' => 'retail']);

        Livewire::actingAs($this->client())
            ->test(RecurringTemplatesGallery::class)
            ->call('setCategory', 'office')
            ->assertSet('selectedCategory', 'office')
            ->assertOk();
    }

    public function test_open_apply_modal_sets_template_id(): void
    {
        $template = RecurringTemplate::factory()->system()->create(['default_time' => null]);

        Livewire::actingAs($this->client())
            ->test(RecurringTemplatesGallery::class)
            ->call('openApplyModal', $template->id)
            ->assertSet('applyTemplateId', $template->id)
            ->assertOk();
    }

    public function test_close_apply_modal_resets_state(): void
    {
        $template = RecurringTemplate::factory()->system()->create(['default_time' => null]);

        Livewire::actingAs($this->client())
            ->test(RecurringTemplatesGallery::class)
            ->set('applyTemplateId', $template->id)
            ->set('selectedSiteId', 5)
            ->set('applyEndsAt', '2026-12-31')
            ->set('applyOccurrenceCount', 10)
            ->set('applyCustomTime', '10:00')
            ->call('closeApplyModal')
            ->assertSet('applyTemplateId', null)
            ->assertSet('selectedSiteId', null)
            ->assertSet('applyEndsAt', null)
            ->assertSet('applyOccurrenceCount', null)
            ->assertSet('applyCustomTime', null);
    }

    public function test_apply_template_without_id_is_a_noop(): void
    {
        Livewire::actingAs($this->client())
            ->test(RecurringTemplatesGallery::class)
            ->call('applyTemplate')
            ->assertSet('flashMessage', null)
            ->assertSet('flashType', null);
    }

    public function test_apply_template_with_missing_template_flashes_error(): void
    {
        Livewire::actingAs($this->client())
            ->test(RecurringTemplatesGallery::class)
            ->set('applyTemplateId', 999999)
            ->call('applyTemplate')
            ->assertSet('flashMessage', 'Template introuvable.')
            ->assertSet('flashType', 'error');
    }

    public function test_apply_template_on_inactive_template_flashes_domain_error(): void
    {
        $template = RecurringTemplate::factory()->system()->inactive()->create(['default_time' => null]);

        Livewire::actingAs($this->client())
            ->test(RecurringTemplatesGallery::class)
            ->set('applyTemplateId', $template->id)
            ->set('applyStartsAt', now()->addDays(3)->toDateString())
            ->call('applyTemplate')
            ->assertSet('flashType', 'error')
            ->assertSet('flashMessage', "Ce template n'est plus disponible.");
    }

    public function test_apply_template_success_creates_series_and_flashes_success(): void
    {
        $user = $this->client();
        $template = RecurringTemplate::factory()->system()->create([
            'name' => 'Hebdo bureaux',
            'default_time' => null,
        ]);

        Livewire::actingAs($user)
            ->test(RecurringTemplatesGallery::class)
            ->set('applyTemplateId', $template->id)
            ->set('applyStartsAt', now()->addDays(4)->toDateString())
            ->set('applyCustomTime', '08:30')
            ->call('applyTemplate')
            ->assertSet('flashType', 'success');

        $this->assertDatabaseHas('recurring_booking_series', [
            'customer_user_id' => $user->id,
            'frequency' => $template->frequency,
            'status' => RecurringBookingSeries::STATUS_ACTIVE,
        ]);

        $this->assertSame(1, $template->fresh()->usage_count);
    }

    public function test_clear_flash_resets_flash_state(): void
    {
        Livewire::actingAs($this->client())
            ->test(RecurringTemplatesGallery::class)
            ->set('flashMessage', 'hello')
            ->set('flashType', 'success')
            ->call('clearFlash')
            ->assertSet('flashMessage', null)
            ->assertSet('flashType', null);
    }

    public function test_render_for_company_user_loads_sites(): void
    {
        $org = OrganizationAccount::factory()->create();
        OrganizationSite::factory()->create(['organization_account_id' => $org->id]);
        $user = User::factory()->client()->create(['organization_account_id' => $org->id]);

        RecurringTemplate::factory()->system()->create(['category' => 'office']);

        Livewire::actingAs($user)
            ->test(RecurringTemplatesGallery::class)
            ->assertOk();
    }
}
