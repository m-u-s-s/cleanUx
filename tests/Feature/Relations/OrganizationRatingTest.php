<?php

namespace Tests\Feature\Relations;

use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Rating\OrganizationRatingAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_rating_is_weighted_average_of_worker_ratings(): void
    {
        $this->assertTrue(Schema::hasColumn('organization_accounts', 'rating_avg'));
        $this->assertTrue(Schema::hasColumn('organization_accounts', 'rating_count'));

        $org = OrganizationAccount::factory()->create();
        foreach ([[4.0, 10], [5.0, 30]] as [$avg, $count]) {
            $u = User::factory()->create(['organization_account_id' => $org->id]);
            ProviderProfile::create([
                'user_id' => $u->id, 'organization_account_id' => $org->id,
                'provider_type' => ProviderType::COMPANY_WORKER->value, 'status' => 'active',
                'verification_status' => 'verified', 'rating_avg' => $avg, 'rating_count' => $count,
            ]);
        }

        app(OrganizationRatingAggregator::class)->recompute($org);

        $this->assertEqualsWithDelta(4.75, (float) $org->fresh()->rating_avg, 0.01);
        $this->assertSame(40, (int) $org->fresh()->rating_count);
    }

    public function test_recompute_command_runs_and_sets_provider_company_rating(): void
    {
        $org = OrganizationAccount::factory()->create(['type' => 'provider_company']);
        $u = User::factory()->create(['organization_account_id' => $org->id]);
        ProviderProfile::create([
            'user_id' => $u->id, 'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value, 'status' => 'active',
            'verification_status' => 'verified', 'rating_avg' => 4.5, 'rating_count' => 8,
        ]);

        $this->artisan('organizations:recompute-ratings')->assertExitCode(0);

        $this->assertEqualsWithDelta(4.5, (float) $org->fresh()->rating_avg, 0.01);
        $this->assertSame(8, (int) $org->fresh()->rating_count);
    }
}
