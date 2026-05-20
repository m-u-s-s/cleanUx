<?php

namespace Tests\Feature\AccountingV2;

use App\Models\AccountingEntry;
use App\Models\AccountingExport;
use App\Services\AccountingV2\AccountingService;
use App\Services\AccountingV2\ExportManager;
use App\Services\AccountingV2\Exports\CsvExportBuilder;
use App\Services\AccountingV2\Exports\FecExportBuilder;
use App\Services\AccountingV2\Exports\QuickBooksIifExportBuilder;
use App\Services\AccountingV2\Exports\SageExportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportBuildersTest extends TestCase
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
        Config::set('accounting_v2.export_retention_days', 0);
        Config::set('accounting_v2.fec', ['country_code' => 'FR', 'siren' => '123456789', 'delimiter' => '|']);
        Config::set('accounting_v2.journals', [
            'VEN' => ['name' => 'Ventes', 'type' => 'sales'],
            'OD' => ['name' => 'OD', 'type' => 'misc'],
        ]);
        Config::set('accounting_v2.chart_of_accounts', [
            '411000' => ['name' => 'Clients', 'class' => 4],
            '701100' => ['name' => 'Ventes', 'class' => 7],
        ]);
        Storage::fake('local');

        app(AccountingService::class)->post([
            ['account_code' => '411000', 'debit_cents' => 12000, 'label' => 'Facture A'],
            ['account_code' => '701100', 'credit_cents' => 12000, 'label' => 'Vente A'],
        ], ['journal_code' => 'VEN', 'reference' => 'BOOK-1']);
    }

    public function test_csv_builder_contains_header_and_row(): void
    {
        $built = (new CsvExportBuilder())->build(AccountingEntry::query());
        $this->assertSame(2, $built['row_count']);
        $this->assertStringContainsString('entry_code;posting_date;journal_code', $built['content']);
        $this->assertStringContainsString('411000', $built['content']);
        $this->assertStringContainsString('701100', $built['content']);
    }

    public function test_fec_builder_uses_pipe_delimiter_and_required_columns(): void
    {
        $built = (new FecExportBuilder())->build(AccountingEntry::query());
        $this->assertStringStartsWith('JournalCode|JournalLib|EcritureNum', $built['content']);
        $this->assertStringContainsString('VEN|Ventes|', $built['content']);
        // Montants au format virgule decimal
        $this->assertStringContainsString('|120,00|', $built['content']);
    }

    public function test_sage_builder_uses_french_date_format(): void
    {
        $built = (new SageExportBuilder())->build(AccountingEntry::query());
        $this->assertStringStartsWith('Date;Journal;Compte', $built['content']);
        $this->assertMatchesRegularExpression('/\d{2}\/\d{2}\/\d{4};VEN;411000/', $built['content']);
    }

    public function test_quickbooks_iif_emits_trns_spl_endtrns_blocks(): void
    {
        $built = (new QuickBooksIifExportBuilder())->build(AccountingEntry::query());
        // Header lines required by IIF spec
        $this->assertStringContainsString("!TRNS\t", $built['content']);
        $this->assertStringContainsString("!SPL\t", $built['content']);
        $this->assertStringContainsString('!ENDTRNS', $built['content']);
        // Data rows : TRNS / SPL / ENDTRNS markers présents
        $this->assertMatchesRegularExpression('/(^|\r\n)TRNS\tbatch_/', $built['content']);
        $this->assertMatchesRegularExpression('/(^|\r\n)SPL\tbatch_/', $built['content']);
        $this->assertMatchesRegularExpression('/(^|\r\n)ENDTRNS/', $built['content']);
    }

    public function test_export_manager_persists_file_with_hash(): void
    {
        $export = app(ExportManager::class)->generate('csv', (int) now()->year);
        $this->assertSame(AccountingExport::STATUS_READY, $export->status);
        $this->assertNotEmpty($export->file_hash);
        $this->assertSame(64, strlen((string) $export->file_hash));
        $this->assertSame(2, $export->row_count);
        Storage::disk('local')->assertExists($export->file_path);
    }
}
