<?php

namespace Tests\Feature;

use App\Livewire\Admin\GestionEntreprises;
use App\Livewire\Client\PrendreRendezVous;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class EntrepriseAdvancedWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesZoneAwareFixtures;

    public function test_admin_can_save_advanced_entreprise_contract_rules(): void
    {
        $admin = User::factory()->admin()->create();
        $context = $this->createCoverageContext();

        Livewire::actingAs($admin)
            ->test(GestionEntreprises::class)
            ->set('name', 'CleanUx Corporate')
            ->set('legal_name', 'CleanUx Corporate SA')
            ->set('slug', 'cleanux-corporate')
            ->set('account_type', 'entreprise')
            ->set('account_status', 'active')
            ->set('approval_mode', 'manual')
            ->set('purchase_order_required', true)
            ->set('default_cost_center', 'CC-BRU-01')
            ->set('negotiated_discount_percent', '12.5')
            ->set('priority_zone_id', (string) $context['zone']->id)
            ->call('saveAccount')
            ->assertHasNoErrors();

        $account = OrganizationAccount::firstOrFail();

        $this->assertSame('manual', data_get($account->metadata, 'approval_mode'));
        $this->assertTrue((bool) data_get($account->metadata, 'purchase_order_required'));
        $this->assertSame('CC-BRU-01', data_get($account->metadata, 'default_cost_center'));
        $this->assertSame(12.5, (float) data_get($account->metadata, 'negotiated_discount_percent'));
    }

    public function test_entreprise_booking_requires_purchase_order_when_contract_demands_it(): void
    {
        $context = $this->createCoverageContext();
        $client = User::factory()->entreprise()->create();
        $account = OrganizationAccount::factory()->create([
            'metadata' => [
                'approval_mode' => 'manual',
                'purchase_order_required' => true,
                'default_cost_center' => 'CC-BRU-01',
            ],
        ]);
        $client->forceFill(['organization_account_id' => $account->id])->save();

        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $account->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'postal_code' => $context['postalCode']->code,
            'city' => $context['postalCode']->city_name,
            'address_line_1' => 'Rue Corporate 1',
            'is_active' => true,
        ]);

        $employee = User::factory()->employe()->create();
        $this->assignEmployeeToZone($employee, $context['zone']);

        Livewire::actingAs($client)
            ->test(PrendreRendezVous::class)
            ->set('selected_service_identifier', $context['service']->code ?: $context['service']->slug)
            ->set('type_lieu', 'bureau')
            ->set('frequence', 'ponctuel')
            ->set('surface', '0_50')
            ->set('organization_site_id', $site->id)
            ->set('adresse', 'Rue Corporate 1')
            ->set('ville', $context['postalCode']->city_name)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('telephone_client', '0470000000')
            ->set('priorite', 'normale')
            ->set('rdvDate', now()->addDays(3)->toDateString())
            ->set('rdvHeure', '09:00')
            ->call('envoyerDemande')
            ->assertHasErrors(['purchase_order_reference']);
    }
}
