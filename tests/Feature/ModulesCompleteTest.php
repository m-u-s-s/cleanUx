<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\AccountingPeriod;
use App\Models\FleetAssignment;
use App\Models\FleetVehicle;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ModulesCompleteTest — covers the 9 items added to bring 5 modules to 100%.
 *
 * Marketing  : campaign stats, step CRUD, A/B config endpoint
 * Fleet      : provider return assignment
 * Accounting : closed period blocks entry deletion
 * Insurance  : client claims list (existing route smoke-test)
 * Webhooks   : old secret rejected after rotation (in WebhookApiTest)
 */
class ModulesCompleteTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create(['is_super_admin' => true]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function makeCampaign(): MarketingCampaign
    {
        return MarketingCampaign::query()->create([
            'code'   => 'camp_' . Str::lower(Str::random(10)),
            'name'   => 'Test Campaign',
            'type'   => 'single_blast',
            'status' => 'draft',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Marketing — campaign stats
    // ─────────────────────────────────────────────────────────────────────────

    public function test_campaign_stats_endpoint_returns_expected_keys(): void
    {
        $this->actingAsAdmin();
        $campaign = $this->makeCampaign();

        $this->getJson("/api/admin/marketing/campaigns/{$campaign->id}/stats")
            ->assertOk()
            ->assertJsonStructure(['total', 'sent', 'failed', 'opted_out', 'pending', 'open_rate', 'click_rate']);
    }

    public function test_campaign_stats_counts_are_zero_on_empty_campaign(): void
    {
        $this->actingAsAdmin();
        $campaign = $this->makeCampaign();

        $response = $this->getJson("/api/admin/marketing/campaigns/{$campaign->id}/stats");
        $response->assertOk();
        $this->assertSame(0, $response->json('total'));
        $this->assertSame(0, $response->json('sent'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Marketing — step CRUD
    // ─────────────────────────────────────────────────────────────────────────

    public function test_campaign_step_create_returns_201(): void
    {
        $this->actingAsAdmin();
        $campaign = $this->makeCampaign();

        $this->postJson("/api/admin/marketing/campaigns/{$campaign->id}/steps", [
            'channel' => 'email',
            'body'    => 'Hello {name}!',
        ])->assertStatus(201)
          ->assertJsonStructure(['data' => ['id', 'campaign_id', 'channel', 'position']]);
    }

    public function test_campaign_step_create_sets_position_incrementally(): void
    {
        $this->actingAsAdmin();
        $campaign = $this->makeCampaign();

        $first  = $this->postJson("/api/admin/marketing/campaigns/{$campaign->id}/steps", ['channel' => 'sms', 'body' => 'A']);
        $second = $this->postJson("/api/admin/marketing/campaigns/{$campaign->id}/steps", ['channel' => 'push', 'body' => 'B']);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame(1, $first->json('data.position'));
        $this->assertSame(2, $second->json('data.position'));
    }

    public function test_campaign_step_update(): void
    {
        $this->actingAsAdmin();
        $campaign = $this->makeCampaign();

        $created = $this->postJson("/api/admin/marketing/campaigns/{$campaign->id}/steps", [
            'channel' => 'email', 'body' => 'Original body',
        ])->assertStatus(201);

        $stepId = $created->json('data.id');

        $this->putJson("/api/admin/marketing/campaigns/{$campaign->id}/steps/{$stepId}", [
            'subject' => 'New Subject',
        ])->assertOk()
          ->assertJsonPath('data.subject', 'New Subject');
    }

    public function test_campaign_step_delete(): void
    {
        $this->actingAsAdmin();
        $campaign = $this->makeCampaign();

        $created = $this->postJson("/api/admin/marketing/campaigns/{$campaign->id}/steps", [
            'channel' => 'push', 'body' => 'Delete me',
        ])->assertStatus(201);

        $stepId = $created->json('data.id');

        $this->deleteJson("/api/admin/marketing/campaigns/{$campaign->id}/steps/{$stepId}")
             ->assertOk()
             ->assertJson(['ok' => true]);

        $this->assertNull(MarketingCampaignStep::find($stepId));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Marketing — A/B config
    // ─────────────────────────────────────────────────────────────────────────

    public function test_campaign_ab_config_patch_persists_variants(): void
    {
        $this->actingAsAdmin();
        $campaign = $this->makeCampaign();

        $this->patchJson("/api/admin/marketing/campaigns/{$campaign->id}/ab-config", [
            'variants' => [
                ['label' => 'A', 'weight' => 0.5],
                ['label' => 'B', 'weight' => 0.5],
            ],
        ])->assertOk()
          ->assertJson(['ok' => true])
          ->assertJsonPath('ab_test_config.0.label', 'A');

        $this->assertCount(2, $campaign->fresh()->ab_test_config);
    }

    public function test_campaign_ab_config_requires_min_two_variants(): void
    {
        $this->actingAsAdmin();
        $campaign = $this->makeCampaign();

        $this->patchJson("/api/admin/marketing/campaigns/{$campaign->id}/ab-config", [
            'variants' => [
                ['label' => 'A', 'weight' => 1.0],
            ],
        ])->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fleet — provider return assignment
    // ─────────────────────────────────────────────────────────────────────────

    public function test_provider_fleet_return_requires_own_assignment(): void
    {
        $provider = User::factory()->employe()->create();
        $other    = User::factory()->employe()->create();
        Sanctum::actingAs($provider);

        // Create a vehicle and assignment owned by $other
        $vehicle = FleetVehicle::query()->create([
            'code'         => 'VH_' . Str::upper(Str::random(5)),
            'vehicle_type' => 'van',
            'brand'        => 'Test',
            'model'        => 'Model',
            'plate'        => 'TEST-001',
            'status'       => 'available',
            'year'         => 2020,
        ]);

        $assignment = FleetAssignment::query()->create([
            'code'             => 'fa_' . Str::lower(Str::random(8)),
            'fleet_vehicle_id' => $vehicle->id,
            'provider_user_id' => $other->id,
            'subject_type'     => 'vehicle',
            'status'           => FleetAssignment::STATUS_ACTIVE,
            'assigned_at'      => now(),
        ]);

        $this->postJson("/api/provider/fleet/assignments/{$assignment->id}/return", [
            'condition' => 'ok',
        ])->assertStatus(403);
    }

    public function test_provider_fleet_return_own_assignment_succeeds(): void
    {
        $provider = User::factory()->employe()->create();
        Sanctum::actingAs($provider);

        $vehicle = FleetVehicle::query()->create([
            'code'         => 'VH_' . Str::upper(Str::random(5)),
            'vehicle_type' => 'van',
            'brand'        => 'Test',
            'model'        => 'ModelX',
            'plate'        => 'TEST-' . Str::random(4),
            'status'       => 'available',
            'year'         => 2021,
        ]);

        $assignment = FleetAssignment::query()->create([
            'code'             => 'fa_' . Str::lower(Str::random(8)),
            'fleet_vehicle_id' => $vehicle->id,
            'provider_user_id' => $provider->id,
            'subject_type'     => 'vehicle',
            'status'           => FleetAssignment::STATUS_ACTIVE,
            'assigned_at'      => now(),
        ]);

        $this->postJson("/api/provider/fleet/assignments/{$assignment->id}/return", [
            'condition' => 'ok',
        ])->assertOk()
          ->assertJson(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Accounting — closed period blocks entry deletion
    // ─────────────────────────────────────────────────────────────────────────

    public function test_delete_entry_in_open_period_succeeds(): void
    {
        $this->actingAsAdmin();

        $entry = AccountingEntry::query()->create([
            'entry_code'   => AccountingEntry::generateEntryCode(),
            'batch_id'     => AccountingEntry::generateBatchId(),
            'posting_date' => now()->startOfMonth(),
            'journal_code' => 'VTE',
            'account_code' => '411000',
            'account_name' => 'Client',
            'debit_cents'  => 1000,
            'credit_cents' => 0,
            'label'        => 'Test entry',
            'currency'     => 'EUR',
        ]);

        $this->deleteJson("/api/admin/accounting-v2/entries/{$entry->id}")
             ->assertOk()
             ->assertJson(['ok' => true]);

        $this->assertNull(AccountingEntry::find($entry->id));
    }

    public function test_delete_entry_in_closed_period_blocked(): void
    {
        $this->actingAsAdmin();

        $year  = (int) now()->format('Y');
        $month = (int) now()->format('m');

        // Create and close the period
        AccountingPeriod::query()->create([
            'period_year'  => $year,
            'period_month' => $month,
            'is_closed'    => true,
            'opened_at'    => now()->subDays(10),
            'closed_at'    => now(),
        ]);

        $entry = AccountingEntry::query()->create([
            'entry_code'   => AccountingEntry::generateEntryCode(),
            'batch_id'     => AccountingEntry::generateBatchId(),
            'posting_date' => now()->startOfMonth(),
            'journal_code' => 'VTE',
            'account_code' => '411000',
            'account_name' => 'Client',
            'debit_cents'  => 2000,
            'credit_cents' => 0,
            'label'        => 'Closed period entry',
            'currency'     => 'EUR',
        ]);

        $this->deleteJson("/api/admin/accounting-v2/entries/{$entry->id}")
             ->assertStatus(422);

        $this->assertNotNull(AccountingEntry::find($entry->id), 'Entry must NOT be deleted');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Insurance — client claims list (smoke test for existing endpoint)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_client_insurance_claims_list_returns_ok(): void
    {
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);

        $this->getJson('/api/client/insurance/claims')
             ->assertOk()
             ->assertJsonStructure(['data']);
    }
}
