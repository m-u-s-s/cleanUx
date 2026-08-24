<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\Trade;
use App\Models\User;
use App\Services\Bookings\BulkBookingImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BulkBookingImporterTest extends TestCase
{
    use RefreshDatabase;

    private function importer(): BulkBookingImporter
    {
        return app(BulkBookingImporter::class);
    }

    private function futureDate(): string
    {
        return Carbon::now()->addWeek()->format('Y-m-d H:i');
    }

    public function test_empty_csv_returns_error_payload(): void
    {
        $orderer = User::factory()->client()->create();
        $org = OrganizationAccount::factory()->create();

        $result = $this->importer()->import($orderer, $org->id, '');

        $this->assertFalse($result['ok']);
        $this->assertSame('empty_csv', $result['error']);
        $this->assertSame(0, $result['created']);
        $this->assertSame([], $result['errors']);
    }

    public function test_valid_row_without_site_creates_booking(): void
    {
        $orderer = User::factory()->client()->create();
        $org = OrganizationAccount::factory()->create();
        $trade = Trade::factory()->create(['code' => 'NETTOYAGE_BUREAUX']);

        $csv = implode("\n", [
            'site_code,trade_code,scheduled_at,duration_minutes,budget_max_eur,notes,external_ref',
            ',NETTOYAGE_BUREAUX,'.$this->futureDate().',180,80,Nettoyage hebdo,',
        ]);

        $result = $this->importer()->import($orderer, $org->id, $csv);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['created_count']);
        $this->assertSame(0, $result['errors_count']);
        $this->assertSame(2, $result['created'][0]['row']);

        $booking = Booking::query()->findOrFail($result['created'][0]['booking_id']);
        $this->assertSame($orderer->id, (int) $booking->client_id);
        $this->assertSame($org->id, (int) $booking->customer_organization_id);
        $this->assertSame('en_attente', $booking->status);
        $this->assertSame(180, (int) $booking->estimated_duration_minutes);
        $this->assertSame(80.0, (float) $booking->devis_estime);
        $this->assertSame('Nettoyage hebdo', $booking->customer_comment);
        $this->assertSame('bulk_csv_import', $booking->matching_snapshot['source']);
        $this->assertSame($orderer->id, (int) $booking->matching_snapshot['imported_by_user_id']);
    }

    public function test_missing_trade_code_records_row_error(): void
    {
        $orderer = User::factory()->client()->create();
        $org = OrganizationAccount::factory()->create();

        $csv = implode("\n", [
            'site_code,trade_code,scheduled_at,duration_minutes,budget_max_eur,notes',
            ',,'.$this->futureDate().',120,60,Sans metier',
        ]);

        $result = $this->importer()->import($orderer, $org->id, $csv);

        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['created_count']);
        $this->assertSame(1, $result['errors_count']);
        $this->assertStringContainsString('trade_code manquant', $result['errors'][0]['error']);
    }

    public function test_unknown_trade_records_row_error(): void
    {
        $orderer = User::factory()->client()->create();
        $org = OrganizationAccount::factory()->create();

        $csv = implode("\n", [
            'trade_code,scheduled_at,duration_minutes,budget_max_eur',
            'DOES_NOT_EXIST,'.$this->futureDate().',60,40',
        ]);

        $result = $this->importer()->import($orderer, $org->id, $csv);

        $this->assertSame(0, $result['created_count']);
        $this->assertSame(1, $result['errors_count']);
        $this->assertStringContainsString('introuvable', $result['errors'][0]['error']);
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_missing_scheduled_at_records_row_error(): void
    {
        $orderer = User::factory()->client()->create();
        $org = OrganizationAccount::factory()->create();
        Trade::factory()->create(['code' => 'PEINTURE']);

        $csv = implode("\n", [
            'trade_code,scheduled_at,duration_minutes',
            'PEINTURE,,90',
        ]);

        $result = $this->importer()->import($orderer, $org->id, $csv);

        $this->assertSame(0, $result['created_count']);
        $this->assertSame(1, $result['errors_count']);
        $this->assertStringContainsString('scheduled_at manquant', $result['errors'][0]['error']);
    }

    public function test_past_date_records_row_error(): void
    {
        $orderer = User::factory()->client()->create();
        $org = OrganizationAccount::factory()->create();
        Trade::factory()->create(['code' => 'TOITURE']);

        $csv = implode("\n", [
            'trade_code,scheduled_at,duration_minutes',
            'TOITURE,2020-01-01 09:00,90',
        ]);

        $result = $this->importer()->import($orderer, $org->id, $csv);

        $this->assertSame(0, $result['created_count']);
        $this->assertSame(1, $result['errors_count']);
        // The past-date guard is re-wrapped by the surrounding catch into the
        // "format invalide" message (see BulkBookingImporter::createBookingFromRow).
        $this->assertStringContainsString('scheduled_at format invalide', $result['errors'][0]['error']);
    }

    public function test_unknown_site_code_records_row_error(): void
    {
        $orderer = User::factory()->client()->create();
        $org = OrganizationAccount::factory()->create();
        Trade::factory()->create(['code' => 'NETTOYAGE']);

        $csv = implode("\n", [
            'site_code,trade_code,scheduled_at,duration_minutes',
            'UNKNOWN-SITE,NETTOYAGE,'.$this->futureDate().',120',
        ]);

        $result = $this->importer()->import($orderer, $org->id, $csv);

        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['created_count']);
        $this->assertSame(1, $result['errors_count']);
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_blank_lines_are_skipped(): void
    {
        $orderer = User::factory()->client()->create();
        $org = OrganizationAccount::factory()->create();
        Trade::factory()->create(['code' => 'VITRES']);

        $csv = implode("\n", [
            'trade_code,scheduled_at,duration_minutes,budget_max_eur',
            'VITRES,'.$this->futureDate().',60,40',
            '',
            '',
        ]);

        $result = $this->importer()->import($orderer, $org->id, $csv);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['created_count']);
        $this->assertSame(0, $result['errors_count']);
    }

    public function test_semicolon_separator_is_supported(): void
    {
        $orderer = User::factory()->client()->create();
        $org = OrganizationAccount::factory()->create();
        Trade::factory()->create(['code' => 'BABYSITTING']);

        $csv = implode("\n", [
            'trade_code;scheduled_at;duration_minutes;budget_max_eur',
            'BABYSITTING;'.$this->futureDate().';120;50',
        ]);

        $result = $this->importer()->import($orderer, $org->id, $csv, ';');

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['created_count']);
        $this->assertSame(0, $result['errors_count']);
    }

    public function test_mixed_rows_soft_fail_per_line(): void
    {
        $orderer = User::factory()->client()->create();
        $org = OrganizationAccount::factory()->create();
        Trade::factory()->create(['code' => 'LEVAGE']);

        $csv = implode("\n", [
            'trade_code,scheduled_at,duration_minutes,budget_max_eur',
            'LEVAGE,'.$this->futureDate().',120,90',
            'NO_SUCH_TRADE,'.$this->futureDate().',60,30',
        ]);

        $result = $this->importer()->import($orderer, $org->id, $csv);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['created_count']);
        $this->assertSame(1, $result['errors_count']);
        $this->assertSame(1, Booking::query()->count());
    }

    public function test_external_ref_is_idempotent(): void
    {
        $orderer = User::factory()->client()->create();
        $org = OrganizationAccount::factory()->create();
        Trade::factory()->create(['code' => 'DEMENAGEMENT']);

        $csv = implode("\n", [
            'trade_code,scheduled_at,duration_minutes,budget_max_eur,external_ref',
            'DEMENAGEMENT,'.$this->futureDate().',120,90,EXT-001',
        ]);

        $first = $this->importer()->import($orderer, $org->id, $csv);
        $second = $this->importer()->import($orderer, $org->id, $csv);

        $this->assertSame(1, $first['created_count']);
        $this->assertSame(1, $second['created_count']);
        // Second import resolves the already-imported external_ref and reuses it.
        $this->assertSame(
            $first['created'][0]['booking_id'],
            $second['created'][0]['booking_id']
        );
        $this->assertSame(1, Booking::query()->count());
    }
}
