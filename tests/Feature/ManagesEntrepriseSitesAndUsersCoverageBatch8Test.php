<?php

namespace Tests\Feature;

use App\Livewire\Admin\GestionEntreprises;
use App\Models\OrganizationSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class ManagesEntrepriseSitesAndUsersCoverageBatch8Test extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    public function test_edit_site_loads_form_fields_from_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $context = $this->createEntrepriseContext();

        $site = $context['site'];
        $site->forceFill([
            'is_primary' => true,
            'is_active' => true,
            'metadata' => [
                'approval_mode' => 'admin',
                'purchase_order_required' => true,
                'default_cost_center' => 'CC-42',
                'priority_level' => 'high',
                'requires_manual_validation' => true,
                'site_tags' => ['alpha', 'beta'],
            ],
        ])->save();

        $this->actingAs($admin);

        Livewire::test(GestionEntreprises::class)
            ->call('selectAccount', $context['account']->id)
            ->call('editSite', $site->id)
            ->assertSet('siteId', $site->id)
            ->assertSet('site_name', (string) $site->name)
            ->assertSet('site_approval_mode', 'admin')
            ->assertSet('site_purchase_order_required', true)
            ->assertSet('site_default_cost_center', 'CC-42')
            ->assertSet('site_priority_level', 'high')
            ->assertSet('site_requires_manual_validation', true)
            ->assertSet('site_tags', 'alpha, beta')
            ->assertSet('site_is_primary', true)
            ->assertSet('site_is_active', true);
    }

    public function test_reset_site_form_restores_defaults(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(GestionEntreprises::class)
            ->set('siteId', 99)
            ->set('site_name', 'Garbage')
            ->set('site_approval_mode', 'admin')
            ->set('site_is_active', false)
            ->set('site_is_primary', true)
            ->call('resetSiteForm')
            ->assertSet('siteId', null)
            ->assertSet('site_name', '')
            ->assertSet('site_approval_mode', 'inherit')
            ->assertSet('site_is_active', true)
            ->assertSet('site_is_primary', false);
    }

    public function test_save_site_returns_early_when_no_account_selected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(GestionEntreprises::class)
            ->set('site_name', 'Orphan Site')
            ->call('saveSite')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('organization_sites', ['name' => 'Orphan Site']);
    }

    public function test_save_site_updates_existing_and_demotes_other_primary(): void
    {
        $admin = User::factory()->admin()->create();
        $context = $this->createEntrepriseContext();

        $secondary = OrganizationSite::create([
            'organization_account_id' => $context['account']->id,
            'client_user_id' => $context['clientUser']->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'name' => 'Secondary Site',
            'site_code' => 'SEC',
            'city' => $context['postalCode']->city_name,
            'postal_code' => $context['postalCode']->code,
            'is_primary' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(GestionEntreprises::class)
            ->call('selectAccount', $context['account']->id)
            ->call('editSite', $secondary->id)
            ->set('site_name', 'Secondary Renamed')
            ->set('site_is_primary', true)
            ->call('saveSite')
            ->assertHasNoErrors();

        $secondary->refresh();
        $this->assertSame('Secondary Renamed', $secondary->name);
        $this->assertTrue((bool) $secondary->is_primary);

        // The originally-primary site must have been demoted.
        $this->assertFalse((bool) $context['site']->fresh()->is_primary);
    }

    public function test_delete_site_removes_record(): void
    {
        $admin = User::factory()->admin()->create();
        $context = $this->createEntrepriseContext();

        $this->actingAs($admin);

        Livewire::test(GestionEntreprises::class)
            ->call('selectAccount', $context['account']->id)
            ->call('deleteSite', $context['site']->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('organization_sites', ['id' => $context['site']->id]);
    }

    public function test_attach_user_with_all_scope_clears_allowed_sites(): void
    {
        $admin = User::factory()->admin()->create();
        $context = $this->createEntrepriseContext();
        $newUser = User::factory()->client()->create();

        $this->actingAs($admin);

        Livewire::test(GestionEntreprises::class)
            ->call('selectAccount', $context['account']->id)
            ->set('user_to_attach', (string) $newUser->id)
            ->set('user_contact_role', 'manager')
            ->set('user_site_scope', 'all')
            ->set('user_site_ids', [(string) $context['site']->id])
            ->call('attachUser')
            ->assertHasNoErrors()
            ->assertSet('user_site_scope', 'all');

        $newUser->refresh();

        $this->assertSame(User::ROLE_ENTREPRISE, $newUser->role);
        $this->assertSame($context['account']->id, $newUser->organization_account_id);
        $this->assertSame('all', data_get($newUser->metadata, 'entreprise_context.site_scope'));
        $this->assertSame([], data_get($newUser->metadata, 'entreprise_context.allowed_site_ids'));
        $this->assertSame('manager', data_get($newUser->metadata, 'entreprise_contact_role'));
    }

    public function test_attach_user_returns_early_without_selected_account(): void
    {
        $admin = User::factory()->admin()->create();
        $newUser = User::factory()->client()->create();

        $this->actingAs($admin);

        Livewire::test(GestionEntreprises::class)
            ->set('user_to_attach', (string) $newUser->id)
            ->set('user_site_scope', 'all')
            ->call('attachUser')
            ->assertHasNoErrors();

        $this->assertNull($newUser->fresh()->organization_account_id);
    }

    public function test_detach_user_resets_role_and_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $context = $this->createEntrepriseContext();
        $newUser = User::factory()->client()->create();

        $this->actingAs($admin);

        $component = Livewire::test(GestionEntreprises::class)
            ->call('selectAccount', $context['account']->id)
            ->set('user_to_attach', (string) $newUser->id)
            ->set('user_contact_role', 'billing')
            ->set('user_site_scope', 'selected')
            ->set('user_site_ids', [(string) $context['site']->id])
            ->call('attachUser')
            ->assertHasNoErrors();

        $newUser->refresh();
        $this->assertSame(User::ROLE_ENTREPRISE, $newUser->role);

        $component->call('detachUser', $newUser->id)
            ->assertHasNoErrors();

        $newUser->refresh();
        $this->assertNull($newUser->organization_account_id);
        $this->assertSame('client', $newUser->role);
        $this->assertNull(data_get($newUser->metadata, 'entreprise_context'));
        $this->assertNull(data_get($newUser->metadata, 'entreprise_contact_role'));
    }
}
