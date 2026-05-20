<?php

namespace Tests\Feature\AccountingV2;

use App\Models\AccountingEntry;
use App\Models\AccountingExport;
use App\Models\AccountingPeriod;
use App\Models\User;
use App\Services\AccountingV2\AccountingService;
use App\Services\AccountingV2\PeriodCloser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('accounting_v2.allowed_journals', ['VEN', 'ACH', 'BANK', 'OD']);
        Config::set('accounting_v2.allowed_formats', ['csv', 'fec', 'sage', 'quickbooks_iif']);
        Config::set('accounting_v2.block_post_on_closed_period', true);
        Config::set('accounting_v2.export_storage_disk', 'local');
        Config::set('accounting_v2.export_path_prefix', 'accounting_exports_test');
        Config::set('accounting_v2.chart_of_accounts', [
            '411000' => ['name' => 'Clients', 'class' => 4],
            '701100' => ['name' => 'Ventes', 'class' => 7],
            '4457' => ['name' => 'TVA', 'class' => 4],
        ]);
        Storage::fake('local');
    }

    public function test_post_entries_via_api(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/accounting-v2/entries', [
            'lines' => [
                ['account_code' => '411000', 'debit_cents' => 5000, 'label' => 'A'],
                ['account_code' => '701100', 'credit_cents' => 5000, 'label' => 'B'],
            ],
            'journal_code' => 'VEN',
        ]);

        $response->assertCreated();
        $this->assertStringStartsWith('batch_', $response->json('batch_id'));
        $this->assertSame(2, AccountingEntry::query()->count());
    }

    public function test_post_entries_rejects_unbalanced(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/accounting-v2/entries', [
            'lines' => [
                ['account_code' => '411000', 'debit_cents' => 5000, 'label' => 'A'],
                ['account_code' => '701100', 'credit_cents' => 4000, 'label' => 'B'],
            ],
        ])->assertStatus(422);
    }

    public function test_list_entries_with_account_filter(): void
    {
        $admin = User::factory()->admin()->create();
        app(AccountingService::class)->post([
            ['account_code' => '411000', 'debit_cents' => 100, 'label' => 'A'],
            ['account_code' => '701100', 'credit_cents' => 100, 'label' => 'B'],
        ], ['journal_code' => 'VEN']);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/accounting-v2/entries?account_code=411000');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_account_balance_returns_signed_balance(): void
    {
        $admin = User::factory()->admin()->create();
        app(AccountingService::class)->post([
            ['account_code' => '411000', 'debit_cents' => 7500, 'label' => 'A'],
            ['account_code' => '701100', 'credit_cents' => 7500, 'label' => 'B'],
        ], ['journal_code' => 'VEN']);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/accounting-v2/account-balance?account_code=411000');
        $response->assertOk();
        $this->assertSame(7500, (int) $response->json('balance_cents'));
    }

    public function test_close_period_via_api(): void
    {
        $admin = User::factory()->admin()->create();
        app(AccountingService::class)->post([
            ['account_code' => '411000', 'debit_cents' => 100, 'label' => 'A'],
            ['account_code' => '701100', 'credit_cents' => 100, 'label' => 'B'],
        ], ['journal_code' => 'VEN']);

        Sanctum::actingAs($admin);
        $year = (int) now()->year;
        $month = (int) now()->month;
        $response = $this->postJson("/api/admin/accounting-v2/periods/{$year}/{$month}/close");
        $response->assertOk();
        $this->assertTrue((bool) $response->json('period.is_closed'));
    }

    public function test_generate_export_returns_ready_status(): void
    {
        $admin = User::factory()->admin()->create();
        app(AccountingService::class)->post([
            ['account_code' => '411000', 'debit_cents' => 100, 'label' => 'A'],
            ['account_code' => '701100', 'credit_cents' => 100, 'label' => 'B'],
        ], ['journal_code' => 'VEN']);

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/admin/accounting-v2/exports', [
            'format' => 'csv',
            'year' => (int) now()->year,
            'month' => (int) now()->month,
        ]);
        $response->assertOk();
        $this->assertSame('ready', $response->json('export.status'));
        $this->assertGreaterThan(0, (int) $response->json('export.row_count'));
    }

    public function test_generate_export_validates_format(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/accounting-v2/exports', [
            'format' => 'invalid',
            'year' => 2026,
        ])->assertStatus(422);
    }

    public function test_post_entries_requires_auth(): void
    {
        $this->postJson('/api/admin/accounting-v2/entries', [
            'lines' => [
                ['account_code' => '411000', 'debit_cents' => 100, 'label' => 'A'],
                ['account_code' => '701100', 'credit_cents' => 100, 'label' => 'B'],
            ],
        ])->assertStatus(401);
    }
}
