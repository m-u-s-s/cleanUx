<?php

namespace Tests\Feature\AccountingV2;

use App\Livewire\Admin\AccountingV2\AccountingCenter;
use App\Models\AccountingEntry;
use App\Models\AccountingExport;
use App\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingCenterCoverageBatch14Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('accounting_v2.allowed_formats', ['csv', 'fec', 'sage', 'quickbooks_iif']);
        Config::set('accounting_v2.export_storage_disk', 'local');
        Config::set('accounting_v2.export_retention_days', 365);
    }

    public function test_mount_seeds_period_filters_to_current_month(): void
    {
        $admin = $this->comptable();

        Livewire::actingAs($admin)
            ->test(AccountingCenter::class)
            ->assertOk()
            ->assertSet('tab', 'ledger')
            ->assertSet('filterYear', (int) now()->year)
            ->assertSet('filterMonth', (int) now()->month)
            ->assertSet('exportYear', (int) now()->year)
            ->assertSet('exportMonth', (int) now()->month);
    }

    public function test_ledger_tab_renders_filtered_entries(): void
    {
        $admin = $this->comptable();

        $this->makeEntry(['journal_code' => 'VEN', 'account_code' => '411000', 'debit_cents' => 5000, 'credit_cents' => 0]);
        $this->makeEntry(['journal_code' => 'ACH', 'account_code' => '401000', 'debit_cents' => 0, 'credit_cents' => 2000]);

        Livewire::actingAs($admin)
            ->test(AccountingCenter::class)
            ->set('filterJournal', 'VEN')
            ->set('filterAccount', '411000')
            ->assertOk()
            ->assertViewHas('kpis')
            ->assertViewHas('items');
    }

    public function test_periods_and_exports_tabs_render(): void
    {
        $admin = $this->comptable();

        AccountingPeriod::query()->create([
            'period_year' => (int) now()->year,
            'period_month' => (int) now()->month,
            'is_closed' => true,
            'opened_at' => now()->startOfMonth(),
            'closed_at' => now(),
            'total_debit_cents' => 1000,
            'total_credit_cents' => 1000,
        ]);
        AccountingExport::query()->create([
            'code' => AccountingExport::generateCode(),
            'format' => 'csv',
            'period_year' => (int) now()->year,
            'period_month' => (int) now()->month,
            'status' => AccountingExport::STATUS_READY,
            'generated_at' => now(),
        ]);

        $component = Livewire::actingAs($admin)
            ->test(AccountingCenter::class)
            ->set('tab', 'periods')
            ->assertOk()
            ->assertViewHas('items');

        $component->set('tab', 'exports')
            ->assertOk()
            ->assertViewHas('items');
    }

    public function test_close_period_dispatches_success_toast_for_balanced_period(): void
    {
        $admin = $this->comptable();
        $year = (int) now()->year;
        $month = (int) now()->month;

        $this->makeEntry(['account_code' => '411000', 'debit_cents' => 7500, 'credit_cents' => 0]);
        $this->makeEntry(['account_code' => '701100', 'debit_cents' => 0, 'credit_cents' => 7500]);

        Livewire::actingAs($admin)
            ->test(AccountingCenter::class)
            ->call('closePeriod', $year, $month)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('accounting_periods', [
            'period_year' => $year,
            'period_month' => $month,
            'is_closed' => true,
        ]);
    }

    public function test_close_period_dispatches_error_toast_when_already_closed(): void
    {
        $admin = $this->comptable();
        $year = (int) now()->year;
        $month = (int) now()->month;

        AccountingPeriod::query()->create([
            'period_year' => $year,
            'period_month' => $month,
            'is_closed' => true,
            'opened_at' => now()->startOfMonth(),
            'closed_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(AccountingCenter::class)
            ->call('closePeriod', $year, $month)
            ->assertDispatched('toast');
    }

    public function test_generate_export_creates_ready_export(): void
    {
        Storage::fake('local');
        $admin = $this->comptable();

        $this->makeEntry(['debit_cents' => 1000, 'credit_cents' => 0]);

        Livewire::actingAs($admin)
            ->test(AccountingCenter::class)
            ->set('exportFormat', 'csv')
            ->call('generateExport')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('accounting_exports', [
            'format' => 'csv',
            'status' => AccountingExport::STATUS_READY,
            'generated_by_user_id' => $admin->id,
        ]);
    }

    public function test_generate_export_dispatches_error_toast_on_unsupported_format(): void
    {
        Storage::fake('local');
        $admin = $this->comptable();

        Livewire::actingAs($admin)
            ->test(AccountingCenter::class)
            ->set('exportFormat', 'pdf')
            ->call('generateExport')
            ->assertDispatched('toast');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeEntry(array $overrides = []): AccountingEntry
    {
        return AccountingEntry::query()->create(array_merge([
            'entry_code' => AccountingEntry::generateEntryCode(),
            'batch_id' => AccountingEntry::generateBatchId(),
            'posting_date' => now(),
            'journal_code' => 'OD',
            'account_code' => '411000',
            'account_name' => 'Clients',
            'debit_cents' => 1000,
            'credit_cents' => 0,
            'label' => 'Coverage entry',
            'currency' => 'EUR',
            'exchange_rate' => 1.0,
            'metadata' => [],
        ], $overrides));
    }

    /**
     * LE CENTRE COMPTABLE EXIGE DÉSORMAIS UNE CAPACITÉ, ET PLUS SEULEMENT « ÊTRE ADMINISTRATEUR ».
     *
     * `manage-accounting` a été introduite pour qu'on puisse confier le grand livre, les clôtures
     * et les exports légaux à un comptable extérieur SANS lui ouvrir l'exploitation. Ces tests
     * employaient `User::factory()->admin()`, qui ne porte aucune capacité : ils recevaient donc un
     * 403 — le garde faisait exactement son travail.
     *
     * On accorde la capacité plutôt que de faire un super-administrateur : un super-admin passe
     * TOUS les gardes, et ces tests cesseraient de mesurer celui-ci.
     */
    private function comptable(): User
    {
        $admin = User::factory()->admin()->create();

        $admin->forceFill(['permissions' => ['manage-accounting']])->save();

        return $admin->refresh();
    }
}
