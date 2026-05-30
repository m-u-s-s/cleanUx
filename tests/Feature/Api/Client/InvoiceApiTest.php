<?php

namespace Tests\Feature\Api\Client;

use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\FinancePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/client/invoices')->assertUnauthorized();
    }

    public function test_index_returns_only_the_authenticated_clients_invoices(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $other = User::factory()->create(['role' => 'client']);
        $mine = FinanceInvoice::factory()->create(['client_id' => $me->id]);
        $theirs = FinanceInvoice::factory()->create(['client_id' => $other->id]);

        Sanctum::actingAs($me);
        $ids = collect($this->getJson('/api/client/invoices')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id), 'cross-client invoice leak (F4)');
    }

    public function test_index_exposes_the_invoice_fields(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        FinanceInvoice::factory()->create(['client_id' => $me->id]);
        Sanctum::actingAs($me);

        $this->getJson('/api/client/invoices')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'number', 'amount', 'balance_due', 'currency', 'status', 'effective_status', 'issued_at', 'due_at']]]);
    }

    public function test_index_filters_by_status(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        FinanceInvoice::factory()->create(['client_id' => $me->id, 'status' => 'paid']);
        FinanceInvoice::factory()->create(['client_id' => $me->id, 'status' => 'overdue']);
        Sanctum::actingAs($me);

        $statuses = collect($this->getJson('/api/client/invoices?status=paid')->assertOk()->json('data'))->pluck('status');
        $this->assertTrue($statuses->every(fn ($s) => $s === 'paid'));
    }

    public function test_show_returns_own_invoice_with_payments_and_reminders(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $invoice = FinanceInvoice::factory()->create(['client_id' => $me->id]);
        Sanctum::actingAs($me);

        $this->getJson("/api/client/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonStructure(['data' => ['id', 'number', 'amount', 'status', 'effective_status', 'payments', 'reminders']]);
    }

    public function test_show_returns_404_for_another_clients_invoice(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $other = User::factory()->create(['role' => 'client']);
        $theirs = FinanceInvoice::factory()->create(['client_id' => $other->id]);
        Sanctum::actingAs($me);

        $this->getJson("/api/client/invoices/{$theirs->id}")->assertStatus(404);
    }

    public function test_download_requires_auth(): void
    {
        $invoice = FinanceInvoice::factory()->create();
        $this->get("/api/client/invoices/{$invoice->id}/pdf")->assertUnauthorized();
    }

    public function test_download_returns_pdf_for_own_invoice(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $invoice = FinanceInvoice::factory()->create(['client_id' => $me->id]);
        Sanctum::actingAs($me);

        $res = $this->get("/api/client/invoices/{$invoice->id}/pdf");
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', strtolower((string) $res->headers->get('content-type')));
    }

    public function test_download_returns_404_for_another_clients_invoice_pdf(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $theirs = FinanceInvoice::factory()->create(['client_id' => User::factory()->create()->id]);
        Sanctum::actingAs($me);

        $this->get("/api/client/invoices/{$theirs->id}/pdf")->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Summary endpoint — full-parity additions
    // ──────────────────────────────────────────────────────────────────────────

    public function test_summary_requires_auth(): void
    {
        $this->getJson('/api/client/invoices/summary')->assertUnauthorized();
    }

    public function test_summary_returns_scoped_counts(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $other = User::factory()->create(['role' => 'client']);

        // My invoices: 1 paid (balance_due=0) + 1 overdue (balance_due > 0)
        $paidAmount = 120.00;
        $overdueAmount = 80.50;

        $paid = FinanceInvoice::factory()->create([
            'client_id' => $me->id,
            'status' => 'paid',
            'balance_due' => 0.00,
            'total_amount' => $paidAmount,
        ]);

        $overdue = FinanceInvoice::factory()->create([
            'client_id' => $me->id,
            'status' => 'overdue',
            'balance_due' => $overdueAmount,
            'total_amount' => $overdueAmount,
            'due_at' => now()->subDays(5),
        ]);

        // Add a payment on the paid invoice so latest_payment_events is non-empty
        FinancePayment::factory()->create([
            'finance_invoice_id' => $paid->id,
            'amount' => $paidAmount,
            'status' => 'paid',
        ]);

        // Another client's invoice — MUST NOT appear in counts
        FinanceInvoice::factory()->create([
            'client_id' => $other->id,
            'status' => 'overdue',
            'balance_due' => 500.00,
        ]);

        Sanctum::actingAs($me);
        $json = $this->getJson('/api/client/invoices/summary')->assertOk()->json();

        // Correct scoped counts
        $this->assertSame(2, $json['summary']['invoices_count'], 'invoices_count must be 2 (my invoices only)');
        $this->assertSame(1, $json['summary']['paid_count'], 'paid_count must be 1');
        $this->assertSame(0, $json['summary']['partial_count'], 'partial_count must be 0');
        $this->assertSame(1, $json['summary']['overdue_count'], 'overdue_count must be 1');
        $this->assertEqualsWithDelta($overdueAmount, $json['summary']['outstanding_total'], 0.01, 'outstanding_total is my overdue balance only');

        // Cross-tenant exclusion: the other client's overdue (500) is NOT included
        $this->assertLessThan(
            200.0,
            $json['summary']['outstanding_total'],
            'Cross-tenant leak: other client overdue balance must NOT be included',
        );

        // payment_health keys present and correct tone (overdue_count = 1 → rose)
        $this->assertArrayHasKey('payment_health', $json);
        $this->assertSame('rose', $json['payment_health']['tone']);
        $this->assertArrayHasKey('label', $json['payment_health']);
        $this->assertArrayHasKey('title', $json['payment_health']);
        $this->assertArrayHasKey('message', $json['payment_health']);

        // latest_payment_events key present
        $this->assertArrayHasKey('latest_payment_events', $json);
        $this->assertNotEmpty($json['latest_payment_events']);
        $firstEvent = $json['latest_payment_events'][0];
        $this->assertArrayHasKey('id', $firstEvent);
        $this->assertArrayHasKey('amount', $firstEvent);
        $this->assertArrayHasKey('status', $firstEvent);
        $this->assertArrayHasKey('reference', $firstEvent);
        $this->assertArrayHasKey('date', $firstEvent);
    }

    public function test_summary_health_is_amber_when_outstanding_and_no_overdue(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        FinanceInvoice::factory()->create([
            'client_id' => $me->id,
            'status' => 'issued',
            'balance_due' => 200.00,
        ]);

        Sanctum::actingAs($me);
        $json = $this->getJson('/api/client/invoices/summary')->assertOk()->json();

        $this->assertSame('amber', $json['payment_health']['tone']);
    }

    public function test_summary_health_is_emerald_when_all_paid(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        FinanceInvoice::factory()->create([
            'client_id' => $me->id,
            'status' => 'paid',
            'balance_due' => 0.00,
        ]);

        Sanctum::actingAs($me);
        $json = $this->getJson('/api/client/invoices/summary')->assertOk()->json();

        $this->assertSame('emerald', $json['payment_health']['tone']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Search breadth
    // ──────────────────────────────────────────────────────────────────────────

    public function test_search_matches_invoice_number_and_booking_reference(): void
    {
        $me = User::factory()->create(['role' => 'client']);

        $booking = Booking::factory()->create([
            'client_id' => $me->id,
            'booking_reference' => 'CUX-TESTREF-999',
        ]);

        $invoice = FinanceInvoice::factory()->create([
            'client_id' => $me->id,
            'rendez_vous_id' => $booking->id,
            'invoice_number' => 'INV-FINDME-001',
        ]);

        // A second invoice with no matching booking reference
        FinanceInvoice::factory()->create([
            'client_id' => $me->id,
            'invoice_number' => 'INV-OTHER-999',
        ]);

        Sanctum::actingAs($me);

        // Search by booking_reference
        $ids = collect(
            $this->getJson('/api/client/invoices?search=TESTREF-999')->assertOk()->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($invoice->id), 'Invoice linked to matching booking_reference must be returned');
        $this->assertCount(1, $ids, 'Only 1 invoice should match booking_reference search');

        // Search by invoice_number still works
        $ids2 = collect(
            $this->getJson('/api/client/invoices?search=FINDME')->assertOk()->json('data')
        )->pluck('id');
        $this->assertTrue($ids2->contains($invoice->id));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // service_name in list response
    // ──────────────────────────────────────────────────────────────────────────

    public function test_list_exposes_service_name(): void
    {
        $me = User::factory()->create(['role' => 'client']);

        // Invoice with no booking — should still return the fallback
        FinanceInvoice::factory()->create(['client_id' => $me->id]);

        Sanctum::actingAs($me);
        $data = $this->getJson('/api/client/invoices')->assertOk()->json('data');

        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('service_name', $data[0], 'service_name key must be present in list items');
        $this->assertSame('Service non précisé', $data[0]['service_name'], 'Fallback service_name for invoice without booking');
    }

    public function test_list_exposes_service_name_from_booking(): void
    {
        $me = User::factory()->create(['role' => 'client']);

        $booking = Booking::factory()->create(['client_id' => $me->id]);

        FinanceInvoice::factory()->create([
            'client_id' => $me->id,
            'rendez_vous_id' => $booking->id,
        ]);

        Sanctum::actingAs($me);
        $data = $this->getJson('/api/client/invoices')->assertOk()->json('data');

        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('service_name', $data[0]);
        // service_display_name falls through to pricing_snapshot.service.name (seeded by factory)
        $this->assertNotEmpty($data[0]['service_name']);
    }
}
