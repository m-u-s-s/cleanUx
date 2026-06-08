<?php

namespace Tests\Feature\Finance;

use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\FinanceQuote;
use App\Models\User;
use App\Notifications\FinanceReminderNotification;
use App\Services\Finance\Concerns\SynchronizesFinanceDocuments;
use App\Services\Finance\FinanceDocumentCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * Concrete host that mounts the trait under test and satisfies the helper
 * methods the trait expects from its using class by delegating to the real
 * FinanceDocumentCalculator. This mirrors how FinanceDocumentService composes
 * the same behaviour, but exercises the trait code path directly.
 */
class FinanceDocumentSyncHost
{
    use SynchronizesFinanceDocuments;

    public function __construct(protected FinanceDocumentCalculator $calculator) {}

    public function amountBreakdownFor(Booking $rdv): array
    {
        return $this->calculator->amountBreakdownFor($rdv);
    }

    public function nextQuoteNumber(Booking $rdv): string
    {
        return $this->calculator->nextQuoteNumber($rdv);
    }

    public function nextInvoiceNumber(Booking $rdv): string
    {
        return $this->calculator->nextInvoiceNumber($rdv);
    }

    public function quoteStatusFor(Booking $rdv): string
    {
        return $this->calculator->quoteStatusFor($rdv);
    }

    public function invoiceStatusFor(Booking $rdv): string
    {
        return $this->calculator->invoiceStatusFor($rdv);
    }

    public function snapshotFor(Booking $rdv): array
    {
        return $this->calculator->snapshotFor($rdv);
    }
}

class SynchronizesFinanceDocumentsTraitTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    private function host(): FinanceDocumentSyncHost
    {
        return new FinanceDocumentSyncHost(app(FinanceDocumentCalculator::class));
    }

    private function makeBooking(string $stateMethod, array $overrides = []): Booking
    {
        $context = $this->createCoverageContext();

        $rdv = Booking::factory()->{$stateMethod}()->create(array_merge([
            'service_catalog_id' => $context['service']->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'devis_estime' => 100,
            'duree' => 120,
        ], $overrides));

        return $rdv->fresh(['serviceCatalog', 'serviceZone', 'postalCode', 'client', 'organizationAccount']);
    }

    public function test_sync_quote_creates_quote_with_amounts_and_accepted_at_for_confirmed(): void
    {
        $rdv = $this->makeBooking('confirme');

        $quote = $this->host()->syncQuoteForRendezVous($rdv);

        $this->assertInstanceOf(FinanceQuote::class, $quote);
        $this->assertSame($rdv->id, $quote->rendez_vous_id);
        $this->assertSame('EUR', $quote->currency);
        $this->assertSame('accepted', $quote->status);
        $this->assertNotNull($quote->accepted_at);
        $this->assertNotNull($quote->valid_until);
        // base_price 100 + default zone travel_surcharge 5, no corporate discount.
        $this->assertEqualsWithDelta(105.0, (float) $quote->subtotal, 0.01);
        $this->assertSame('particulier', $quote->meta['market']);
        $this->assertArrayHasKey('finance_breakdown', $quote->snapshot);
    }

    public function test_sync_quote_is_idempotent_and_keeps_quote_number(): void
    {
        $rdv = $this->makeBooking('confirme');
        $host = $this->host();

        $first = $host->syncQuoteForRendezVous($rdv);
        $second = $host->syncQuoteForRendezVous($rdv);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->quote_number, $second->quote_number);
        $this->assertSame(1, FinanceQuote::query()->where('rendez_vous_id', $rdv->id)->count());
    }

    public function test_sync_quote_for_pending_has_no_accepted_at_and_draft_status(): void
    {
        $rdv = $this->makeBooking('enAttente');

        $quote = $this->host()->syncQuoteForRendezVous($rdv);

        $this->assertSame('draft', $quote->status);
        $this->assertNull($quote->accepted_at);
    }

    public function test_sync_invoice_returns_null_for_ineligible_status(): void
    {
        $rdv = $this->makeBooking('enAttente');

        $this->assertNull($this->host()->syncInvoiceForRendezVous($rdv));
        $this->assertSame(0, FinanceInvoice::query()->count());
    }

    public function test_sync_invoice_creates_invoice_linked_to_quote_for_eligible(): void
    {
        $rdv = $this->makeBooking('termine');

        $invoice = $this->host()->syncInvoiceForRendezVous($rdv);

        $this->assertInstanceOf(FinanceInvoice::class, $invoice);
        $this->assertSame($rdv->id, $invoice->rendez_vous_id);
        $this->assertNotNull($invoice->finance_quote_id);
        $this->assertSame('EUR', $invoice->currency);
        $this->assertNotNull($invoice->due_at);
        $this->assertEqualsWithDelta((float) $invoice->total_amount, (float) $invoice->balance_due, 0.01);
        $this->assertSame('termine', $invoice->meta['source_status']);
    }

    public function test_sync_documents_for_rows_skips_non_rendezvous_instances(): void
    {
        // The trait guards on `instanceof RendezVous`, an undefined class in its
        // namespace, so every Booking row is skipped and counts stay at zero.
        $rows = [$this->makeBooking('termine'), $this->makeBooking('confirme')];

        $result = $this->host()->syncDocumentsForRows($rows);

        $this->assertSame(['quotes' => 0, 'invoices' => 0], $result);
        $this->assertSame(0, FinanceQuote::query()->count());
    }

    public function test_issue_invoice_sets_status_and_due_date(): void
    {
        $invoice = FinanceInvoice::factory()->create([
            'status' => 'draft',
            'issued_at' => null,
            'due_at' => null,
        ]);

        $due = Carbon::now()->addDays(21);
        $issued = $this->host()->issueInvoice($invoice, $due);

        $this->assertSame('issued', $issued->status);
        $this->assertNotNull($issued->issued_at);
        $this->assertSame($due->toDateString(), $issued->due_at->toDateString());
    }

    public function test_issue_invoice_defaults_due_date_when_not_provided(): void
    {
        $invoice = FinanceInvoice::factory()->create([
            'status' => 'draft',
            'issued_at' => null,
            'due_at' => null,
        ]);

        $issued = $this->host()->issueInvoice($invoice);

        $this->assertSame('issued', $issued->status);
        $this->assertNotNull($issued->due_at);
    }

    public function test_record_payment_marks_invoice_paid_when_full(): void
    {
        $invoice = FinanceInvoice::factory()->create([
            'total_amount' => 200,
            'balance_due' => 200,
            'status' => 'issued',
        ]);

        $payment = $this->host()->recordPayment($invoice, 200, [
            'method' => 'card',
            'provider' => 'stripe',
        ]);

        $this->assertSame('card', $payment->method);
        $this->assertSame('stripe', $payment->provider);
        $this->assertEqualsWithDelta(200.0, (float) $payment->amount, 0.01);
        $this->assertStringStartsWith('PAY-', $payment->payment_reference);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->balance_due, 0.01);
    }

    public function test_record_payment_partial_marks_invoice_partial(): void
    {
        $invoice = FinanceInvoice::factory()->create([
            'total_amount' => 200,
            'balance_due' => 200,
            'status' => 'issued',
        ]);

        $this->host()->recordPayment($invoice, 50);

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertEqualsWithDelta(150.0, (float) $invoice->balance_due, 0.01);
    }

    public function test_send_reminder_creates_sent_reminder_and_notifies_client(): void
    {
        Notification::fake();

        $client = User::factory()->client()->create(['email' => 'billing-client@example.test']);
        $invoice = FinanceInvoice::factory()->create([
            'client_id' => $client->id,
            'status' => 'issued',
            'balance_due' => 120,
        ]);

        $reminder = $this->host()->sendReminder($invoice, 'gentle');

        $this->assertSame('gentle', $reminder->reminder_type);
        $this->assertSame('mail', $reminder->channel);
        $this->assertSame('sent', $reminder->status);
        $this->assertNotNull($reminder->sent_at);
        $this->assertSame('billing-client@example.test', $reminder->recipient_email);
        Notification::assertSentTo($client, FinanceReminderNotification::class);
    }

    public function test_sync_all_eligible_counts_quotes_and_invoices(): void
    {
        $this->makeBooking('termine');
        $this->makeBooking('enAttente');

        $result = $this->host()->syncAllEligible();

        $this->assertSame(2, $result['quotes']);
        $this->assertSame(1, $result['invoices']);
    }

    public function test_send_due_reminders_sends_for_outstanding_invoices_once(): void
    {
        Notification::fake();

        $client = User::factory()->client()->create(['email' => 'due-client@example.test']);
        FinanceInvoice::factory()->create([
            'client_id' => $client->id,
            'status' => 'issued',
            'balance_due' => 90,
            'due_at' => Carbon::now()->subDays(3),
        ]);

        $host = $this->host();
        $sent = $host->sendDueReminders();
        $this->assertSame(1, $sent);

        // Running again within the dedup window should send nothing new.
        $again = $host->sendDueReminders();
        $this->assertSame(0, $again);
    }
}
