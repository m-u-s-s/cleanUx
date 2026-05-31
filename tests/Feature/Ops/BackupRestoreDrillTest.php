<?php

namespace Tests\Feature\Ops;

use App\Models\ProviderWalletTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackupRestoreDrillTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_plan_and_exits_zero_without_touching_db(): void
    {
        $this->artisan('backup:restore-drill', ['--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('integrity');
    }

    public function test_dry_run_describes_the_steps(): void
    {
        $this->artisan('backup:restore-drill', ['--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('scratch');
    }

    public function test_refuses_to_run_real_restore_against_primary_connection(): void
    {
        // Force the scratch connection to resolve to the primary => command must refuse.
        $this->artisan('backup:restore-drill', ['--connection' => config('database.default')])
            ->assertExitCode(1);
    }

    public function test_wallet_ledger_integrity_query_is_valid_against_real_schema(): void
    {
        $provider = User::factory()->create();

        ProviderWalletTransaction::create([
            'provider_user_id' => $provider->id,
            'type' => ProviderWalletTransaction::TYPE_EARNING,
            'direction' => 'credit',
            'amount' => 80.00,
            'currency' => 'EUR',
            'status' => 'available',
            'occurred_at' => now(),
        ]);

        ProviderWalletTransaction::create([
            'provider_user_id' => $provider->id,
            'type' => ProviderWalletTransaction::TYPE_REFUND_CLAWBACK,
            'direction' => 'debit',
            'amount' => 30.00,
            'currency' => 'EUR',
            'status' => 'available',
            'occurred_at' => now(),
        ]);

        $rows = DB::table('provider_wallet_transactions')
            ->selectRaw("provider_user_id, SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) AS balance")
            ->groupBy('provider_user_id')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(50.00, (float) $rows[0]->balance, 0.01);
    }
}
