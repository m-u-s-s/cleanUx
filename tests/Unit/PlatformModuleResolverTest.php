<?php

namespace Tests\Unit;

use App\Models\OrganizationAccount;
use App\Models\PlatformModule;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Modules\PlatformModuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformModuleResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_role_and_plan_based_module_activation(): void
    {
        $module = PlatformModule::factory()->create([
            'rollout_strategy' => 'role',
            'is_enabled' => true,
            'settings' => [
                'allowed_roles' => ['client'],
                'allowed_plans' => ['premium'],
            ],
        ]);

        $resolver = app(PlatformModuleResolver::class);

        $premiumClient = User::factory()->premiumClient()->create();
        $standardClient = User::factory()->client()->create();
        $employe = User::factory()->employe()->create();

        $this->assertTrue($resolver->isEnabledFor($module, $premiumClient));
        $this->assertFalse($resolver->isEnabledFor($module, $standardClient));
        $this->assertFalse($resolver->isEnabledFor($module, $employe));
    }

    public function test_it_resolves_zone_and_organization_scopes(): void
    {
        $zoneA = ServiceZone::factory()->create();
        $zoneB = ServiceZone::factory()->create();
        $organizationA = OrganizationAccount::factory()->create();
        $organizationB = OrganizationAccount::factory()->create();

        $module = PlatformModule::factory()->create([
            'rollout_strategy' => 'organization',
            'is_enabled' => true,
            'settings' => [
                'allowed_organization_ids' => [$organizationA->id],
                'allowed_zone_ids' => [$zoneA->id],
            ],
        ]);

        $resolver = app(PlatformModuleResolver::class);

        $goodUser = User::factory()->entreprise()->create([
            'organization_account_id' => $organizationA->id,
            'primary_service_zone_id' => $zoneA->id,
        ]);

        $wrongOrgUser = User::factory()->entreprise()->create([
            'organization_account_id' => $organizationB->id,
            'primary_service_zone_id' => $zoneA->id,
        ]);

        $wrongZoneUser = User::factory()->entreprise()->create([
            'organization_account_id' => $organizationA->id,
            'primary_service_zone_id' => $zoneB->id,
        ]);

        $this->assertTrue($resolver->isEnabledFor($module, $goodUser));
        $this->assertFalse($resolver->isEnabledFor($module, $wrongOrgUser));
        $this->assertFalse($resolver->isEnabledFor($module, $wrongZoneUser));
    }
}
