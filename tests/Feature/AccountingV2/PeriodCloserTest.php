<?php

namespace Tests\Feature\AccountingV2;

use App\Models\AccountingPeriod;
use App\Models\User;
use App\Services\AccountingV2\AccountingService;
use App\Services\AccountingV2\PeriodCloser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PeriodCloserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('accounting_v2.allowed_journals', ['VEN', 'ACH', 'BANK', 'OD']);
        Config::set('accounting_v2.block_post_on_closed_period', true);
        Config::set('accounting_v2.chart_of_accounts', [
            '411000' => ['name' => 'Clients', 'class' => 4],
            '701100' => ['name' => 'Ventes', 'class' => 7],
        ]);
    }

    public function test_close_persists_totals_and_is_closed(): void
    {
        $admin = User::factory()->admin()->create();
        $year = (int) now()->year;
        $month = (int) now()->month;
        app(AccountingService::class)->post([
            ['account_code' => '411000', 'debit_cents' => 5000, 'label' => 'A'],
            ['account_code' => '701100', 'credit_cents' => 5000, 'label' => 'B'],
        ], ['journal_code' => 'VEN']);

        $period = app(PeriodCloser::class)->close($year, $month, $admin);

        $this->assertTrue((bool) $period->is_closed);
        $this->assertSame(5000, $period->total_debit_cents);
        $this->assertSame(5000, $period->total_credit_cents);
        $this->assertSame(2, $period->entry_count);
        $this->assertSame($admin->id, $period->closed_by_user_id);
    }

    public function test_close_rejects_already_closed(): void
    {
        $admin = User::factory()->admin()->create();
        $year = (int) now()->year;
        $month = (int) now()->month;
        app(PeriodCloser::class)->close($year, $month, $admin);

        $this->expectException(ValidationException::class);
        app(PeriodCloser::class)->close($year, $month, $admin);
    }

    public function test_post_blocked_on_closed_period(): void
    {
        $admin = User::factory()->admin()->create();
        $year = (int) now()->year;
        $month = (int) now()->month;
        app(PeriodCloser::class)->close($year, $month, $admin);

        $this->expectException(ValidationException::class);
        app(AccountingService::class)->post([
            ['account_code' => '411000', 'debit_cents' => 100, 'label' => 'X'],
            ['account_code' => '701100', 'credit_cents' => 100, 'label' => 'Y'],
        ]);
    }

    public function test_reopen_requires_reason_min_length(): void
    {
        $admin = User::factory()->admin()->create();
        $year = (int) now()->year;
        $month = (int) now()->month;
        $period = app(PeriodCloser::class)->close($year, $month, $admin);

        $this->expectException(ValidationException::class);
        app(PeriodCloser::class)->reopen($period, $admin, 'no');
    }

    public function test_reopen_sets_is_closed_false_with_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $year = (int) now()->year;
        $month = (int) now()->month;
        $period = app(PeriodCloser::class)->close($year, $month, $admin);

        $reopened = app(PeriodCloser::class)->reopen($period, $admin, 'Erreur d\'écriture détectée par auditeur externe');
        $this->assertFalse((bool) $reopened->is_closed);
        $this->assertNotNull(data_get($reopened->metadata, 'reopen_reason'));
    }
}
