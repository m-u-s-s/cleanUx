<?php

namespace Tests\Feature\Finance;

use App\Livewire\Client\FinanceDocumentsClient;
use App\Models\FinanceInvoice;
use App\Models\FinancePayment;
use App\Models\User;
use App\Support\Finance\ClientInvoiceSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Parity guard: ClientInvoiceSummary::for() (mobile API) must return numbers that match the Livewire web component's computed properties for the same seeded dataset. */
class ClientInvoiceSummaryParityTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Seed a deterministic dataset for $me: - 1 PAID invoice with a FinancePayment (balance_due = 0) - 1 OVERDUE invoice (status='overdue', balance_due > 0, due_at in the past) - 1 PARTIAL invoice (balance_due > 0, status='partial') Also creates another client's invoice to prove isolation.
     *
     * @return array{paid: FinanceInvoice, overdue: FinanceInvoice, partial: FinanceInvoice, payment: FinancePayment, other_invoice: FinanceInvoice}
     */
    private function seedDeterministicDataset(User $me): array
    {
        $other = User::factory()->create(['role' => 'client']);

        $paid = FinanceInvoice::factory()->create([
            'client_id' => $me->id,
            'status' => 'paid',
            'balance_due' => 0.00,
            'total_amount' => 200.00,
            'issued_at' => now()->subDays(10),
            'due_at' => now()->subDays(3),
            'currency' => 'EUR',
        ]);

        $payment = FinancePayment::factory()->create([
            'finance_invoice_id' => $paid->id,
            'amount' => 200.00,
            'status' => 'paid',
            'paid_at' => now()->subDays(3),
        ]);

        $overdue = FinanceInvoice::factory()->create([
            'client_id' => $me->id,
            'status' => 'overdue',
            'balance_due' => 80.50,
            'total_amount' => 80.50,
            'issued_at' => now()->subDays(20),
            'due_at' => now()->subDays(5),
            'currency' => 'EUR',
        ]);

        $partial = FinanceInvoice::factory()->create([
            'client_id' => $me->id,
            'status' => 'partial',
            'balance_due' => 50.25,
            'total_amount' => 100.50,
            'issued_at' => now()->subDays(5),
            'due_at' => now()->addDays(10),
            'currency' => 'EUR',
        ]);

        // Another client's invoice — must be excluded by both sides
        $otherInvoice = FinanceInvoice::factory()->create([
            'client_id' => $other->id,
            'status' => 'overdue',
            'balance_due' => 999.00,
            'total_amount' => 999.00,
            'issued_at' => now()->subDays(1),
            'due_at' => now()->subDays(1),
            'currency' => 'EUR',
        ]);

        return compact('paid', 'overdue', 'partial', 'payment', 'otherInvoice');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tests
    // ──────────────────────────────────────────────────────────────────────────

    /** Core parity test: invoice-level counts and outstanding total must be identical between the API class and the Livewire computed property. */
    #[Test]
    public function invoice_summary_counts_match_livewire_computed_property(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $this->seedDeterministicDataset($me);

        // API side
        $api = ClientInvoiceSummary::for($me);
        $apiSummary = $api['summary'];

        // Livewire side — call the real computed method via instance()
        $component = Livewire::actingAs($me)
            ->test(FinanceDocumentsClient::class)
            ->instance();

        $lwSummary = $component->getFinanceSummaryProperty();

        // Assert overlapping invoice-count keys are equal
        $this->assertSame(
            $apiSummary['invoices_count'],
            $lwSummary['invoices_count'],
            'invoices_count diverged between API and Livewire',
        );

        $this->assertSame(
            $apiSummary['paid_count'],
            $lwSummary['paid_count'],
            'paid_count diverged between API and Livewire',
        );

        $this->assertSame(
            $apiSummary['partial_count'],
            $lwSummary['partial_count'],
            'partial_count diverged between API and Livewire',
        );

        $this->assertSame(
            $apiSummary['overdue_count'],
            $lwSummary['overdue_count'],
            'overdue_count diverged between API and Livewire',
        );

        $this->assertEqualsWithDelta(
            $apiSummary['outstanding_total'],
            (float) $lwSummary['outstanding_total'],
            0.01,
            'outstanding_total diverged between API and Livewire',
        );

        // Sanity: the dataset is what we expect (3 invoices, not polluted by other client)
        $this->assertSame(3, $apiSummary['invoices_count'], 'Expected exactly 3 invoices for $me');
        $this->assertSame(1, $apiSummary['paid_count']);
        $this->assertSame(1, $apiSummary['partial_count']);
        $this->assertSame(1, $apiSummary['overdue_count']);
        $this->assertEqualsWithDelta(130.75, $apiSummary['outstanding_total'], 0.01, 'outstanding_total should be 80.50 + 50.25');
    }

    /** Payment-health tone, label, title, and message must be identical between the API class and the Livewire computed property. */
    #[Test]
    public function payment_health_matches_livewire_computed_property(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $this->seedDeterministicDataset($me);

        $api = ClientInvoiceSummary::for($me);
        $apiHealth = $api['payment_health'];

        $component = Livewire::actingAs($me)
            ->test(FinanceDocumentsClient::class)
            ->instance();

        $lwHealth = $component->getPaymentHealthProperty();

        $this->assertSame(
            $apiHealth['tone'],
            $lwHealth['tone'],
            'payment_health.tone diverged between API and Livewire',
        );

        $this->assertSame(
            $apiHealth['label'],
            $lwHealth['label'],
            'payment_health.label diverged between API and Livewire',
        );

        $this->assertSame(
            $apiHealth['title'],
            $lwHealth['title'],
            'payment_health.title diverged between API and Livewire',
        );

        $this->assertSame(
            $apiHealth['message'],
            $lwHealth['message'],
            'payment_health.message diverged between API and Livewire',
        );

        // Concrete value check (overdue present → rose)
        $this->assertSame('rose', $apiHealth['tone']);
    }

    /** When there are no overdue invoices but an outstanding balance, both sides must return tone='amber'. */
    #[Test]
    public function payment_health_amber_matches_when_outstanding_no_overdue(): void
    {
        $me = User::factory()->create(['role' => 'client']);

        FinanceInvoice::factory()->create([
            'client_id' => $me->id,
            'status' => 'issued',
            'balance_due' => 150.00,
            'total_amount' => 150.00,
            'issued_at' => now()->subDays(1),
            'due_at' => now()->addDays(15),
        ]);

        $apiHealth = ClientInvoiceSummary::for($me)['payment_health'];

        $lwHealth = Livewire::actingAs($me)
            ->test(FinanceDocumentsClient::class)
            ->instance()
            ->getPaymentHealthProperty();

        $this->assertSame($apiHealth['tone'], $lwHealth['tone']);
        $this->assertSame($apiHealth['label'], $lwHealth['label']);
        $this->assertSame($apiHealth['title'], $lwHealth['title']);
        $this->assertSame($apiHealth['message'], $lwHealth['message']);
        $this->assertSame('amber', $apiHealth['tone']);
    }

    /** When all invoices are paid, both sides must return tone='emerald'. */
    #[Test]
    public function payment_health_emerald_matches_when_all_paid(): void
    {
        $me = User::factory()->create(['role' => 'client']);

        FinanceInvoice::factory()->create([
            'client_id' => $me->id,
            'status' => 'paid',
            'balance_due' => 0.00,
            'total_amount' => 100.00,
            'issued_at' => now()->subDays(5),
        ]);

        $apiHealth = ClientInvoiceSummary::for($me)['payment_health'];

        $lwHealth = Livewire::actingAs($me)
            ->test(FinanceDocumentsClient::class)
            ->instance()
            ->getPaymentHealthProperty();

        $this->assertSame($apiHealth['tone'], $lwHealth['tone']);
        $this->assertSame($apiHealth['label'], $lwHealth['label']);
        $this->assertSame($apiHealth['title'], $lwHealth['title']);
        $this->assertSame($apiHealth['message'], $lwHealth['message']);
        $this->assertSame('emerald', $apiHealth['tone']);
    }

    /** latest_payment_events count and ordering must match between API and Livewire. */
    #[Test]
    public function latest_payment_events_count_and_order_match_livewire(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $this->seedDeterministicDataset($me);

        $apiEvents = ClientInvoiceSummary::for($me)['latest_payment_events'];

        $lwEvents = Livewire::actingAs($me)
            ->test(FinanceDocumentsClient::class)
            ->instance()
            ->getLatestPaymentEventsProperty();

        $this->assertCount(
            count($apiEvents),
            $lwEvents,
            'latest_payment_events count diverged between API and Livewire',
        );

        if (count($apiEvents) > 0 && $lwEvents->count() > 0) {
            $this->assertSame(
                $apiEvents[0]['id'],
                (int) $lwEvents->first()->id,
                'latest_payment_events first item id diverged (ordering differs)',
            );
        }
    }

    /** Cross-tenant isolation: the other client's invoice must be excluded by BOTH sides identically. */
    #[Test]
    public function both_sides_exclude_other_clients_invoices(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $this->seedDeterministicDataset($me);

        $apiSummary = ClientInvoiceSummary::for($me)['summary'];

        $lwSummary = Livewire::actingAs($me)
            ->test(FinanceDocumentsClient::class)
            ->instance()
            ->getFinanceSummaryProperty();

        // The other client has 1 overdue invoice (999.00) — if it leaked into
        // either side, invoices_count would be 4 and outstanding_total would
        // exceed 1000. Both sides must agree and be scoped to $me only.
        $this->assertSame(3, $apiSummary['invoices_count'], 'API: other client invoice leaked into count');
        $this->assertSame(3, $lwSummary['invoices_count'], 'Livewire: other client invoice leaked into count');

        $this->assertLessThan(200.0, $apiSummary['outstanding_total'], 'API: other client balance leaked into outstanding_total');
        $this->assertLessThan(200.0, (float) $lwSummary['outstanding_total'], 'Livewire: other client balance leaked into outstanding_total');
    }
}
