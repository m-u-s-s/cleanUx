<?php

namespace Tests\Feature\AccountingV2;

use App\Models\AccountingEntry;
use App\Services\AccountingV2\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('accounting_v2.allowed_journals', ['VEN', 'ACH', 'BANK', 'OD', 'INV']);
        Config::set('accounting_v2.block_post_on_closed_period', true);
        Config::set('accounting_v2.chart_of_accounts', [
            '411000' => ['name' => 'Clients généraux', 'class' => 4],
            '512100' => ['name' => 'Banque Stripe', 'class' => 5],
            '701100' => ['name' => 'Ventes bookings', 'class' => 7],
            '4457' => ['name' => 'TVA collectée', 'class' => 4],
            '627' => ['name' => 'Frais Stripe', 'class' => 6],
        ]);
    }

    public function test_post_balanced_batch_persists_entries_with_shared_batch_id(): void
    {
        $batchId = app(AccountingService::class)->post([
            ['account_code' => '411000', 'debit_cents' => 12000, 'label' => 'Facture A'],
            ['account_code' => '701100', 'credit_cents' => 10000, 'label' => 'Vente A'],
            ['account_code' => '4457', 'credit_cents' => 2000, 'label' => 'TVA A'],
        ], ['journal_code' => 'VEN']);

        $this->assertStringStartsWith('batch_', $batchId);
        $entries = AccountingEntry::query()->where('batch_id', $batchId)->get();
        $this->assertCount(3, $entries);
        $this->assertSame(12000, (int) $entries->sum('debit_cents'));
        $this->assertSame(12000, (int) $entries->sum('credit_cents'));
    }

    public function test_post_rejects_unbalanced_batch(): void
    {
        $this->expectException(ValidationException::class);
        app(AccountingService::class)->post([
            ['account_code' => '411000', 'debit_cents' => 12000, 'label' => 'A'],
            ['account_code' => '701100', 'credit_cents' => 5000, 'label' => 'B'],
        ]);
    }

    public function test_post_rejects_line_with_both_debit_and_credit(): void
    {
        $this->expectException(ValidationException::class);
        app(AccountingService::class)->post([
            ['account_code' => '411000', 'debit_cents' => 100, 'credit_cents' => 100, 'label' => 'A'],
            ['account_code' => '701100', 'credit_cents' => 0, 'label' => 'B'],
        ]);
    }

    public function test_post_rejects_unknown_account(): void
    {
        $this->expectException(ValidationException::class);
        app(AccountingService::class)->post([
            ['account_code' => '999999', 'debit_cents' => 100, 'label' => 'A'],
            ['account_code' => '701100', 'credit_cents' => 100, 'label' => 'B'],
        ]);
    }

    public function test_post_rejects_unknown_journal(): void
    {
        $this->expectException(ValidationException::class);
        app(AccountingService::class)->post([
            ['account_code' => '411000', 'debit_cents' => 100, 'label' => 'A'],
            ['account_code' => '701100', 'credit_cents' => 100, 'label' => 'B'],
        ], ['journal_code' => 'XXX']);
    }

    public function test_post_idempotent_returns_existing_batch(): void
    {
        $svc = app(AccountingService::class);
        $lines = [
            ['account_code' => '411000', 'debit_cents' => 1000, 'label' => 'A'],
            ['account_code' => '701100', 'credit_cents' => 1000, 'label' => 'B'],
        ];
        $a = $svc->postIdempotent('Booking', 42, $lines);
        $b = $svc->postIdempotent('Booking', 42, $lines);

        $this->assertSame($a, $b);
        $this->assertSame(2, AccountingEntry::query()->count());
    }

    public function test_balance_for_account_returns_signed_balance(): void
    {
        $svc = app(AccountingService::class);
        $svc->post([
            ['account_code' => '411000', 'debit_cents' => 15000, 'label' => 'Facture'],
            ['account_code' => '701100', 'credit_cents' => 15000, 'label' => 'Vente'],
        ]);

        $balance = $svc->balanceForAccount('411000');
        $this->assertSame(15000, $balance['debit_cents']);
        $this->assertSame(0, $balance['credit_cents']);
        $this->assertSame(15000, $balance['balance_cents']);
    }
}
