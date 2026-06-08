<?php

namespace Tests\Feature\Finance;

use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Finance\Concerns\BuildsFinanceSnapshotsAndAmounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Exercises the BuildsFinanceSnapshotsAndAmounts trait in isolation.
 *
 * The trait is consumed via a tiny in-file harness so we can call both its
 * public (amountBreakdownFor / invoiceHealthSummary) and protected
 * (quoteStatusFor / invoiceStatusFor / snapshotFor / nextQuoteNumber /
 * nextInvoiceNumber) members directly.
 *
 * Bookings and their relations are built in memory (setRelation) so the money
 * maths is asserted deterministically; the one DB-backed path is the default
 * FinanceInvoice query inside invoiceHealthSummary().
 */
class FinanceSnapshotsAndAmountsTraitTest extends TestCase
{
    use RefreshDatabase;

    private function harness(): object
    {
        return new class
        {
            use BuildsFinanceSnapshotsAndAmounts {
                quoteStatusFor as public;
                invoiceStatusFor as public;
                snapshotFor as public;
                nextQuoteNumber as public;
                nextInvoiceNumber as public;
            }
        };
    }

    // ──────────────────────────────────────────────────────────────────────
    // amountBreakdownFor
    // ──────────────────────────────────────────────────────────────────────

    #[Test]
    public function it_builds_breakdown_from_pricing_snapshot_for_a_private_client(): void
    {
        $rdv = new Booking([
            'pricing_snapshot' => [
                'devis_estime' => 100,
                'travel_surcharge' => 10,
                'tax_rate' => 21,
                'duration_minutes' => 60,
            ],
        ]);

        $out = $this->harness()->amountBreakdownFor($rdv);

        $this->assertSame(100.0, $out['base_price']);
        $this->assertSame(10.0, $out['travel_surcharge']);
        $this->assertSame(0.0, $out['discount_rate']);
        $this->assertSame(0.0, $out['discount_amount']);
        $this->assertSame(110.0, $out['subtotal']);
        $this->assertSame(21.0, $out['tax_rate']);
        $this->assertSame(23.1, $out['tax_amount']);
        $this->assertSame(133.1, $out['total_amount']);
        $this->assertSame(60, $out['duration_minutes']);
        $this->assertSame(18.0, $out['employee_hourly_cost']);
        $this->assertSame(0.0, $out['fixed_mission_cost']);
        $this->assertSame(18.0, $out['estimated_internal_cost']);
        $this->assertSame(92.0, $out['estimated_margin_amount']);
        $this->assertSame(83.6, $out['estimated_margin_rate']);
        // No organization → private-client payment terms / default validity.
        $this->assertSame(14, $out['payment_terms_days']);
        $this->assertSame(15, $out['quote_validity_days']);
    }

    #[Test]
    public function it_applies_corporate_discount_and_organization_finance_metadata(): void
    {
        $org = new OrganizationAccount(['name' => 'Acme NV']);
        $org->metadata = [
            'finance' => [
                'tax_rate' => 20,
                'default_employee_hourly_cost' => 25,
                'fixed_mission_cost' => 5,
                'quote_validity_days' => 30,
            ],
        ];

        $rdv = new Booking([
            'organization_account_id' => 5,
            'pricing_snapshot' => [
                'base_price' => 200,
                'corporate_context' => [
                    'negotiated_discount_rate' => 10,
                    'payment_terms_days' => 45,
                ],
            ],
        ]);
        $rdv->duree = 120; // not mass-assignable
        $rdv->setRelation('organizationAccount', $org);

        $out = $this->harness()->amountBreakdownFor($rdv);

        $this->assertSame(200.0, $out['base_price']);
        $this->assertSame(10.0, $out['discount_rate']);
        $this->assertSame(20.0, $out['discount_amount']);
        $this->assertSame(180.0, $out['subtotal']);
        $this->assertSame(20.0, $out['tax_rate']);
        $this->assertSame(36.0, $out['tax_amount']);
        $this->assertSame(216.0, $out['total_amount']);
        $this->assertSame(120, $out['duration_minutes']);
        $this->assertSame(25.0, $out['employee_hourly_cost']);
        $this->assertSame(5.0, $out['fixed_mission_cost']);
        $this->assertSame(55.0, $out['estimated_internal_cost']);
        $this->assertSame(125.0, $out['estimated_margin_amount']);
        $this->assertSame(69.4, $out['estimated_margin_rate']);
        // corporate_context wins for payment terms; org metadata for validity.
        $this->assertSame(45, $out['payment_terms_days']);
        $this->assertSame(30, $out['quote_validity_days']);
    }

    #[Test]
    public function it_falls_back_to_catalog_zone_and_employee_relations(): void
    {
        $catalog = new ServiceCatalog(['name' => 'Deep Clean']);
        $catalog->base_price = 150;
        $catalog->default_duration_minutes = 90;

        $zone = new ServiceZone(['name' => 'Zone A']);
        $zone->travel_surcharge = 12;

        $employe = new User;
        $employe->metadata = ['finance' => ['hourly_cost' => 30]];

        $rdv = new Booking;
        $rdv->setRelation('serviceCatalog', $catalog);
        $rdv->setRelation('serviceZone', $zone);
        $rdv->setRelation('employe', $employe);

        $out = $this->harness()->amountBreakdownFor($rdv);

        $this->assertSame(150.0, $out['base_price']);
        $this->assertSame(12.0, $out['travel_surcharge']);
        $this->assertSame(162.0, $out['subtotal']);
        $this->assertSame(21.0, $out['tax_rate']);
        $this->assertSame(34.02, $out['tax_amount']);
        $this->assertSame(196.02, $out['total_amount']);
        $this->assertSame(90, $out['duration_minutes']);
        $this->assertSame(30.0, $out['employee_hourly_cost']);
        $this->assertSame(45.0, $out['estimated_internal_cost']);
        $this->assertSame(117.0, $out['estimated_margin_amount']);
        $this->assertSame(72.2, $out['estimated_margin_rate']);
    }

    #[Test]
    public function it_guards_zero_subtotal_so_margin_rate_is_zero(): void
    {
        $rdv = new Booking;

        $out = $this->harness()->amountBreakdownFor($rdv);

        $this->assertSame(0.0, $out['base_price']);
        $this->assertSame(0.0, $out['subtotal']);
        $this->assertSame(21.0, $out['tax_rate']);
        $this->assertSame(0.0, $out['tax_amount']);
        $this->assertSame(0.0, $out['total_amount']);
        $this->assertSame(0, $out['duration_minutes']);
        $this->assertSame(0.0, $out['estimated_internal_cost']);
        // $subtotal > 0 is false → the guard branch returns the float literal.
        $this->assertSame(0.0, $out['estimated_margin_rate']);
        $this->assertSame(14, $out['payment_terms_days']);
        $this->assertSame(15, $out['quote_validity_days']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // invoiceHealthSummary
    // ──────────────────────────────────────────────────────────────────────

    #[Test]
    public function it_summarises_a_supplied_invoice_collection(): void
    {
        $rows = new Collection([
            $this->invoice(['balance_due' => 0, 'total_amount' => 200, 'status' => 'paid', 'due_at' => now()->subDay()]),
            $this->invoice(['balance_due' => 80, 'total_amount' => 80, 'status' => 'overdue', 'due_at' => now()->subDays(5)]),
            $this->invoice(['balance_due' => 50, 'total_amount' => 100, 'status' => 'partial', 'due_at' => now()->addDays(5)]),
            $this->invoice(['balance_due' => 30, 'total_amount' => 30, 'status' => 'sent', 'due_at' => now()->addDays(10)]),
        ]);

        $summary = $this->harness()->invoiceHealthSummary($rows);

        $this->assertSame(4, $summary['invoice_count']);
        $this->assertSame(160.0, $summary['outstanding_balance']);
        $this->assertSame(200.0, $summary['paid_total']);
        $this->assertSame(1, $summary['overdue_count']);
        $this->assertSame(80.0, $summary['overdue_balance']);
        $this->assertSame(1, $summary['partial_count']);
    }

    #[Test]
    public function it_queries_the_database_when_no_collection_is_supplied(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        FinanceInvoice::factory()->create([
            'client_id' => $client->id,
            'status' => 'paid',
            'balance_due' => 0.00,
            'total_amount' => 120.00,
            'due_at' => now()->subDays(2),
        ]);
        FinanceInvoice::factory()->create([
            'client_id' => $client->id,
            'status' => 'overdue',
            'balance_due' => 75.00,
            'total_amount' => 75.00,
            'due_at' => now()->subDays(3),
        ]);

        $summary = $this->harness()->invoiceHealthSummary();

        $this->assertSame(2, $summary['invoice_count']);
        $this->assertSame(75.0, $summary['outstanding_balance']);
        $this->assertSame(120.0, $summary['paid_total']);
        $this->assertSame(1, $summary['overdue_count']);
        $this->assertSame(75.0, $summary['overdue_balance']);
    }

    private function invoice(array $attributes): FinanceInvoice
    {
        $invoice = new FinanceInvoice;
        foreach ($attributes as $key => $value) {
            $invoice->{$key} = $value;
        }

        return $invoice;
    }

    // ──────────────────────────────────────────────────────────────────────
    // status mapping + snapshot + numbering
    // ──────────────────────────────────────────────────────────────────────

    #[Test]
    public function it_maps_booking_status_to_quote_status(): void
    {
        $h = $this->harness();

        $this->assertSame('cancelled', $h->quoteStatusFor(new Booking(['status' => 'annule'])));
        $this->assertSame('cancelled', $h->quoteStatusFor(new Booking(['status' => 'refuse'])));
        $this->assertSame('accepted', $h->quoteStatusFor(new Booking(['status' => 'confirme'])));
        $this->assertSame('accepted', $h->quoteStatusFor(new Booking(['status' => 'termine'])));
        $this->assertSame('draft', $h->quoteStatusFor(new Booking(['status' => 'en_attente'])));
    }

    #[Test]
    public function it_maps_booking_status_to_invoice_status(): void
    {
        $h = $this->harness();

        $this->assertSame('issued', $h->invoiceStatusFor(new Booking(['status' => 'termine'])));
        $this->assertSame('sent', $h->invoiceStatusFor(new Booking(['status' => 'en_route'])));
        $this->assertSame('sent', $h->invoiceStatusFor(new Booking(['status' => 'sur_place'])));
        $this->assertSame('draft', $h->invoiceStatusFor(new Booking(['status' => 'confirme'])));
    }

    #[Test]
    public function it_builds_a_document_snapshot_from_booking_and_relations(): void
    {
        $org = new OrganizationAccount(['name' => 'Acme NV']);
        $site = new OrganizationSite(['name' => 'HQ']);
        $zone = new ServiceZone(['name' => 'Zone A']);
        $client = new User(['name' => 'Jane Client']);

        $rdv = new Booking([
            'booking_reference' => 'BK-001',
            'heure' => '09:30',
            'adresse' => '1 rue de Test',
            'ville' => 'Bruxelles',
            'pricing_snapshot' => ['service_name' => 'Window Cleaning'],
        ]);
        $rdv->service_type = 'nettoyage'; // not mass-assignable
        $rdv->date = Carbon::parse('2026-06-01');
        $rdv->setRelation('serviceZone', $zone);
        $rdv->setRelation('organizationAccount', $org);
        $rdv->setRelation('organizationSite', $site);
        $rdv->setRelation('client', $client);

        $snapshot = $this->harness()->snapshotFor($rdv);

        $this->assertSame('BK-001', $snapshot['booking_reference']);
        $this->assertSame('Window Cleaning', $snapshot['service_name']);
        $this->assertSame('nettoyage', $snapshot['service_type']);
        $this->assertSame('2026-06-01', $snapshot['date']);
        $this->assertSame('09:30', $snapshot['heure']);
        $this->assertSame('1 rue de Test', $snapshot['adresse']);
        $this->assertSame('Bruxelles', $snapshot['ville']);
        $this->assertSame('Zone A', $snapshot['zone_name']);
        $this->assertSame('Acme NV', $snapshot['organization_name']);
        $this->assertSame('HQ', $snapshot['site_name']);
        $this->assertSame('Jane Client', $snapshot['client_name']);
    }

    #[Test]
    public function it_formats_quote_and_invoice_numbers_with_padded_id(): void
    {
        $h = $this->harness();
        $year = now()->format('Y');

        $rdv = new Booking;
        $rdv->id = 42;

        $this->assertSame("DEV-{$year}-000042", $h->nextQuoteNumber($rdv));
        $this->assertSame("FAC-{$year}-000042", $h->nextInvoiceNumber($rdv));

        // No id yet → padded zero.
        $fresh = new Booking;
        $this->assertSame("DEV-{$year}-000000", $h->nextQuoteNumber($fresh));
    }
}
