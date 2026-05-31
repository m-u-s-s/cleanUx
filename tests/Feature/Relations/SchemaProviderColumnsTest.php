<?php

namespace Tests\Feature\Relations;

use App\Models\Mission;
use App\Models\OrganizationAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaProviderColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_org_and_team_columns_exist_and_are_fillable(): void
    {
        $this->assertTrue(Schema::hasColumn('bookings', 'assigned_provider_organization_id'));
        $this->assertTrue(Schema::hasColumn('bookings', 'provider_team_id'));
        $this->assertTrue(Schema::hasColumn('missions', 'provider_organization_id'));
        $this->assertTrue(Schema::hasColumn('missions', 'provider_team_id'));

        $org = OrganizationAccount::factory()->create();
        $mission = Mission::create([
            'status' => 'planned',
            'provider_organization_id' => $org->id,
        ]);

        $this->assertSame($org->id, $mission->fresh()->provider_organization_id);
        $this->assertInstanceOf(OrganizationAccount::class, $mission->providerOrganization);
    }
}
