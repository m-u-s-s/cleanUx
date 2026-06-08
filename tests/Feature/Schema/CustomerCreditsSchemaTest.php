<?php

namespace Tests\Feature\Schema;

use App\Models\CustomerCredit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M6 — customer_credits consolidated on the credit-per-booking model; dead wallet columns and the
 * orphan ledger table are gone, and the canonical model still works.
 */
class CustomerCreditsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dead_wallet_columns_and_table_are_removed(): void
    {
        $this->assertFalse(Schema::hasColumn('customer_credits', 'balance'));
        $this->assertFalse(Schema::hasColumn('customer_credits', 'currency'));
        $this->assertFalse(Schema::hasTable('customer_credit_transactions'));
    }

    public function test_canonical_credit_per_booking_model_still_works(): void
    {
        $client = User::factory()->client()->create();

        $credit = CustomerCredit::create([
            'client_id' => $client->id,
            'type' => 'referral',
            'amount' => 25.0,
            'remaining_amount' => 25.0,
            'status' => 'active',
            'reason' => 'test',
        ]);

        $this->assertDatabaseHas('customer_credits', [
            'id' => $credit->id,
            'client_id' => $client->id,
            'amount' => 25.0,
            'remaining_amount' => 25.0,
            'status' => 'active',
        ]);
    }
}
