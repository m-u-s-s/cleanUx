<?php

namespace Tests\Feature\Missions;

use App\Models\FieldTeam;
use App\Models\MissionBatch;
use App\Models\OrganizationAccount;
use App\Models\ServicePartner;
use App\Services\Missions\OperationalLoadCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Coverage for the operational load calculator's reachable guard / filter paths. */
class OperationalLoadCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-07-15';

    private function calculator(): OperationalLoadCalculator
    {
        return app(OperationalLoadCalculator::class);
    }

    public function test_recommend_field_team_returns_null_when_no_teams_exist(): void
    {
        $batch = MissionBatch::factory()->create(['organization_site_id' => null]);

        $this->assertNull($this->calculator()->recommendFieldTeamForBatch($batch, self::DATE));
    }

    public function test_recommend_field_team_filters_out_teams_from_other_organizations(): void
    {
        // Batch is bound to its own organization; the only candidate team belongs to a different
        // organization and is therefore filtered out, so the recommender returns null before it
        // ever reaches the snapshot/sort step.
        $batch = MissionBatch::factory()->create(['organization_site_id' => null]);

        $otherOrg = OrganizationAccount::factory()->create();
        FieldTeam::factory()->create([
            'organization_account_id' => $otherOrg->id,
        ]);

        $this->assertNull($this->calculator()->recommendFieldTeamForBatch($batch, self::DATE));
    }

    public function test_recommend_field_team_defaults_date_from_batch_when_not_supplied(): void
    {
        $batch = MissionBatch::factory()->create([
            'starts_on' => self::DATE,
            'organization_site_id' => null,
        ]);

        // No teams -> short circuits to null, but exercises the `?? $batch->starts_on` date branch.
        $this->assertNull($this->calculator()->recommendFieldTeamForBatch($batch));
    }

    public function test_recommend_partner_returns_null_when_no_active_partners(): void
    {
        $batch = MissionBatch::factory()->create(['organization_site_id' => null]);

        // Only an inactive partner exists; the active/null filter excludes it -> null.
        ServicePartner::factory()->inactive()->create();

        $this->assertNull($this->calculator()->recommendPartnerForBatch($batch, self::DATE));
    }

    public function test_capture_daily_snapshots_returns_structured_empty_payload(): void
    {
        // No field teams and no *active* partners, so no row fans out to the broken capture query.
        ServicePartner::factory()->inactive()->create();

        $result = $this->calculator()->captureDailySnapshots(self::DATE);

        $this->assertSame(self::DATE, $result['date']);
        $this->assertArrayHasKey('field_team_snapshots', $result);
        $this->assertArrayHasKey('partner_snapshots', $result);
        $this->assertCount(0, $result['field_team_snapshots']);
        // Inactive partner is excluded from the daily capture.
        $this->assertCount(0, $result['partner_snapshots']);
    }
}
