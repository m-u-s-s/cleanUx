<?php

namespace Tests\Feature;

use App\Livewire\Admin\GestionEntreprises;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class GestionEntreprisesEnterpriseTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    public function test_admin_can_save_contract_metadata_and_zone_priorities(): void
    {
        $admin = User::factory()->admin()->create();
        $primaryContext = $this->createZoneContext();
        $secondaryContext = $this->createZoneContext();

        $this->actingAs($admin);

        Livewire::test(GestionEntreprises::class)
            ->set('name', 'CleanUx Corporate')
            ->set('legal_name', 'CleanUx Corporate SA')
            ->set('account_type', 'entreprise')
            ->set('account_status', 'active')
            ->set('postal_code', $primaryContext['postalCode']->code)
            ->set('contract_reference', 'CTR-2026-001')
            ->set('pricing_profile', 'corporate-a')
            ->set('sla_hours', '6')
            ->set('approval_mode', 'hybrid')
            ->set('payment_terms_days', '45')
            ->set('negotiated_discount_percent', '12.5')
            ->set('contract_status_value', 'active')
            ->set('zone_priority_ids', [(string) $primaryContext['zone']->id, (string) $secondaryContext['zone']->id])
            ->set('require_po', true)
            ->set('default_cost_center', 'HQ-BRU')
            ->call('saveAccount')
            ->assertHasNoErrors();

        $account = OrganizationAccount::where('name', 'CleanUx Corporate')->firstOrFail();

        $this->assertSame('active', data_get($account->metadata, 'contract_status'));
        $this->assertSame('hybrid', data_get($account->metadata, 'approval_mode'));
        $this->assertEquals([ $primaryContext['zone']->id, $secondaryContext['zone']->id ], $account->priority_zone_ids);
        $this->assertTrue((bool) data_get($account->metadata, 'require_po'));
        $this->assertSame('HQ-BRU', data_get($account->metadata, 'default_cost_center'));
    }

    public function test_admin_can_attach_entreprise_user_with_site_scope(): void
    {
        $admin = User::factory()->admin()->create();
        $context = $this->createEntrepriseContext();
        $extraSite = OrganizationSite::create([
            'organization_account_id' => $context['account']->id,
            'client_user_id' => $context['clientUser']->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'name' => 'Second Site',
            'site_code' => 'SITE-B',
            'city' => $context['postalCode']->city_name,
            'postal_code' => $context['postalCode']->code,
            'is_primary' => false,
            'is_active' => true,
        ]);

        $newUser = User::factory()->client()->create();

        $this->actingAs($admin);

        Livewire::test(GestionEntreprises::class)
            ->call('selectAccount', $context['account']->id)
            ->set('user_to_attach', (string) $newUser->id)
            ->set('user_role_mode', 'entreprise')
            ->set('user_contact_role', 'billing')
            ->set('user_site_scope', 'selected')
            ->set('user_site_ids', [(string) $context['site']->id, (string) $extraSite->id])
            ->call('attachUser')
            ->assertHasNoErrors();

        $newUser->refresh();

        $this->assertSame(User::ROLE_ENTREPRISE, $newUser->role);
        $this->assertSame($context['account']->id, $newUser->organization_account_id);
        $this->assertSame('billing', data_get($newUser->metadata, 'entreprise_context.contact_role'));
        $this->assertSame('selected', data_get($newUser->metadata, 'entreprise_context.site_scope'));
        $this->assertEqualsCanonicalizing([
            $context['site']->id,
            $extraSite->id,
        ], data_get($newUser->metadata, 'entreprise_context.allowed_site_ids'));
    }

    public function test_admin_can_create_multisite_with_metadata_and_auto_zone_resolution(): void
    {
        $admin = User::factory()->admin()->create();
        $context = $this->createEntrepriseContext();

        $this->actingAs($admin);

        Livewire::test(GestionEntreprises::class)
            ->call('selectAccount', $context['account']->id)
            ->set('site_name', 'Brussels Ops')
            ->set('site_code', 'BRU-OPS')
            ->set('site_postal_code', $context['postalCode']->code)
            ->set('site_priority_level', 'critical')
            ->set('site_requires_manual_validation', true)
            ->set('site_tags', 'open-space, night-shift')
            ->call('saveSite')
            ->assertHasNoErrors();

        $site = OrganizationSite::where('organization_account_id', $context['account']->id)
            ->where('site_code', 'BRU-OPS')
            ->firstOrFail();

        $this->assertSame($context['zone']->id, $site->service_zone_id);
        $this->assertSame('critical', data_get($site->metadata, 'priority_level'));
        $this->assertTrue((bool) data_get($site->metadata, 'requires_manual_validation'));
        $this->assertEquals(['open-space', 'night-shift'], data_get($site->metadata, 'site_tags'));
    }
}
