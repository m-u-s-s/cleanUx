<?php

namespace Tests\Feature\Finance;

use App\Livewire\Admin\FinanceCenter;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class FinanceCenterDocumentsHandlingTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    private function makeBooking(array $context, string $state, array $overrides = []): Booking
    {
        $client = User::factory()->client()->create();
        $employee = User::factory()->employe()->create();

        $factory = Booking::factory();
        $factory = match ($state) {
            'confirme' => $factory->confirme(),
            'termine' => $factory->termine(),
            default => $factory->enAttente(),
        };

        return $factory->create(array_merge([
            'client_id' => $client->id,
            'employe_id' => $employee->id,
            'service_catalog_id' => $context['service']->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'devis_estime' => 100,
            'date' => now()->addDays(2)->toDateString(),
        ], $overrides));
    }

    public function test_ensure_quote_document_syncs_quote_and_selects_rendezvous(): void
    {
        $context = $this->createCoverageContext();
        $admin = User::factory()->admin()->create();
        $rdv = $this->makeBooking($context, 'en_attente');

        $this->actingAs($admin);

        Livewire::test(FinanceCenter::class)
            ->call('ensureQuoteDocument', $rdv->id)
            ->assertHasNoErrors()
            ->assertSet('selectedRendezVousId', $rdv->id);

        $this->assertDatabaseHas('finance_quotes', ['rendez_vous_id' => $rdv->id]);
    }

    public function test_ensure_invoice_document_warns_for_non_billable_status(): void
    {
        $context = $this->createCoverageContext();
        $admin = User::factory()->admin()->create();
        $rdv = $this->makeBooking($context, 'en_attente');

        $this->actingAs($admin);

        Livewire::test(FinanceCenter::class)
            ->call('ensureInvoiceDocument', $rdv->id)
            ->assertHasNoErrors()
            ->assertSet('selectedRendezVousId', 0);

        $this->assertDatabaseMissing('finance_invoices', ['rendez_vous_id' => $rdv->id]);
    }

    public function test_full_invoice_lifecycle_issue_pay_and_already_settled(): void
    {
        Notification::fake();
        $context = $this->createCoverageContext();
        $admin = User::factory()->admin()->create();
        $rdv = $this->makeBooking($context, 'confirme');

        $this->actingAs($admin);

        $component = Livewire::test(FinanceCenter::class)
            ->call('ensureInvoiceDocument', $rdv->id)
            ->assertHasNoErrors()
            ->assertSet('selectedRendezVousId', $rdv->id)
            ->call('issueInvoiceNow', $rdv->id)
            ->assertHasNoErrors();

        $this->assertSame('issued', $rdv->fresh()->financeInvoice->status);

        $component->call('sendInvoiceReminderNow', $rdv->id, 'gentle')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('finance_reminders', ['reminder_type' => 'gentle']);

        $component->call('markInvoicePaidNow', $rdv->id)
            ->assertHasNoErrors();

        $invoice = $rdv->fresh()->financeInvoice;
        $this->assertLessThanOrEqual(0.0, (float) $invoice->balance_due);

        // Second call hits the "already settled" branch (no new payment recorded).
        $paymentsBefore = $invoice->payments()->count();
        $component->call('markInvoicePaidNow', $rdv->id)
            ->assertHasNoErrors();
        $this->assertSame($paymentsBefore, $rdv->fresh()->financeInvoice->payments()->count());
        $this->assertLessThanOrEqual(0.0, (float) $rdv->fresh()->financeInvoice->balance_due);
    }

    public function test_record_partial_payment_reduces_balance(): void
    {
        $context = $this->createCoverageContext();
        $admin = User::factory()->admin()->create();
        $rdv = $this->makeBooking($context, 'confirme');

        $this->actingAs($admin);

        Livewire::test(FinanceCenter::class)
            ->call('ensureInvoiceDocument', $rdv->id)
            ->set('manualPaymentAmount', '40')
            ->set('manualPaymentMethod', 'bank_transfer')
            ->call('recordPartialPaymentNow', $rdv->id)
            ->assertHasNoErrors();

        $invoice = $rdv->fresh()->financeInvoice;
        $this->assertSame('partial', $invoice->status);
        $this->assertGreaterThan(0.0, (float) $invoice->balance_due);
        $this->assertDatabaseHas('finance_payments', [
            'finance_invoice_id' => $invoice->id,
            'method' => 'bank_transfer',
        ]);
    }

    public function test_record_partial_payment_rejects_invalid_amount(): void
    {
        $context = $this->createCoverageContext();
        $admin = User::factory()->admin()->create();
        $rdv = $this->makeBooking($context, 'confirme');

        $this->actingAs($admin);

        Livewire::test(FinanceCenter::class)
            ->call('ensureInvoiceDocument', $rdv->id)
            ->set('manualPaymentAmount', '0')
            ->call('recordPartialPaymentNow', $rdv->id)
            ->assertHasNoErrors();

        // Invalid amount: no payment recorded, balance unchanged (full total).
        $this->assertSame(0, $rdv->fresh()->financeInvoice->payments()->count());
    }

    public function test_actions_warn_when_no_invoice_can_be_generated(): void
    {
        $context = $this->createCoverageContext();
        $admin = User::factory()->admin()->create();
        $rdv = $this->makeBooking($context, 'en_attente');

        $this->actingAs($admin);

        Livewire::test(FinanceCenter::class)
            ->call('issueInvoiceNow', $rdv->id)
            ->assertHasNoErrors()
            ->call('markInvoicePaidNow', $rdv->id)
            ->assertHasNoErrors()
            ->call('recordPartialPaymentNow', $rdv->id)
            ->assertHasNoErrors()
            ->call('sendInvoiceReminderNow', $rdv->id)
            ->assertHasNoErrors()
            ->assertSet('selectedRendezVousId', 0);

        // Non-billable status never produces an invoice through any guarded action.
        $this->assertDatabaseMissing('finance_invoices', ['rendez_vous_id' => $rdv->id]);
    }

    public function test_sync_filtered_and_all_documents(): void
    {
        $context = $this->createCoverageContext();
        $admin = User::factory()->admin()->create();
        $rdv = $this->makeBooking($context, 'confirme');

        $this->actingAs($admin);

        Livewire::test(FinanceCenter::class)
            ->set('dateFrom', now()->subYear()->toDateString())
            ->set('dateTo', now()->addYear()->toDateString())
            ->call('syncFilteredDocuments')
            ->assertHasNoErrors()
            ->call('syncAllDocuments')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('finance_quotes', ['rendez_vous_id' => $rdv->id]);
        $this->assertDatabaseHas('finance_invoices', ['rendez_vous_id' => $rdv->id]);
    }

    public function test_export_finance_csv_streams_rows(): void
    {
        $context = $this->createCoverageContext();
        $rdv = $this->makeBooking($context, 'confirme');

        $component = new FinanceCenter;
        $component->dateFrom = now()->subYear()->toDateString();
        $component->dateTo = now()->addYear()->toDateString();

        $response = $component->exportFinanceCsv();

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('reference', $csv);
        $this->assertStringContainsString((string) $rdv->fresh()->booking_reference, $csv);
    }

    public function test_download_quote_and_invoice_pdf(): void
    {
        $context = $this->createCoverageContext();
        $rdv = $this->makeBooking($context, 'confirme');

        $component = new FinanceCenter;

        $quoteResponse = $component->downloadQuotePdf($rdv->id);
        $this->assertInstanceOf(StreamedResponse::class, $quoteResponse);

        ob_start();
        $quoteResponse->sendContent();
        $quotePdf = ob_get_clean();
        $this->assertStringStartsWith('%PDF', $quotePdf);

        $invoiceResponse = $component->downloadInvoicePdf($rdv->id);
        $this->assertInstanceOf(StreamedResponse::class, $invoiceResponse);

        ob_start();
        $invoiceResponse->sendContent();
        $invoicePdf = ob_get_clean();
        $this->assertStringStartsWith('%PDF', $invoicePdf);
    }
}
