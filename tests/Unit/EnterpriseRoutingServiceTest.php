<?php

namespace Tests\Unit;

use App\Models\OrganizationSite;
use App\Models\User;
use App\Services\Enterprise\EnterpriseRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

class EnterpriseRoutingServiceTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    public function test_it_prefers_site_zone_then_account_priorities(): void
    {
        $context = $this->createEntrepriseContext();
        $secondary = $this->createZoneContext();
        $context['account']->update([
            'metadata' => array_merge((array) $context['account']->metadata, [
                'priority_zone_id' => $secondary['zone']->id,
                'priority_zone_ids' => [$secondary['zone']->id],
            ]),
        ]);

        $site = $context['site'];
        $site->update([
            'metadata' => [
                'zone_priority_ids' => [$secondary['zone']->id],
            ],
        ]);

        $service = app(EnterpriseRoutingService::class);

        $result = $service->resolvePriorityZoneIds($context['account']->fresh(), $site->fresh());

        $this->assertSame([$context['zone']->id, $secondary['zone']->id], $result);
    }

    public function test_it_blocks_user_when_site_scope_is_selected_and_site_not_allowed(): void
    {
        $context = $this->createEntrepriseContext();
        $otherSite = OrganizationSite::create([
            'organization_account_id' => $context['account']->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'name' => 'Other Site',
            'site_code' => 'OTH',
            'city' => $context['postalCode']->city_name,
            'postal_code' => $context['postalCode']->code,
            'is_active' => true,
        ]);

        $user = User::factory()->entreprise()->create([
            'organization_account_id' => $context['account']->id,
            'metadata' => [
                'entreprise_context' => [
                    'site_scope' => 'selected',
                    'allowed_site_ids' => [$context['site']->id],
                ],
            ],
        ]);

        $service = app(EnterpriseRoutingService::class);

        $this->assertTrue($service->userCanAccessSite($user, $context['site']));
        $this->assertFalse($service->userCanAccessSite($user, $otherSite));
    }
}
