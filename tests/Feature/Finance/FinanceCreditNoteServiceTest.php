<?php

namespace Tests\Feature\Finance;

use App\Models\Booking;
use App\Models\FinanceCreditNote;
use App\Models\User;
use App\Services\Finance\FinanceCreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit HIGH — génération d'avoirs sur remboursement (conformité BE/FR).
 */
class FinanceCreditNoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private function booking(float $taxRate = 21.0): Booking
    {
        return Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'currency' => 'EUR',
            'pricing_snapshot' => ['tax_rate' => $taxRate],
        ]);
    }

    public function test_creates_a_numbered_credit_note_for_a_refund(): void
    {
        $booking = $this->booking(taxRate: 21.0);

        $note = app(FinanceCreditNoteService::class)
            ->createForRefund($booking, 12100, 're_test_1', 'requested_by_customer');

        $this->assertSame($booking->id, $note->booking_id);
        $this->assertSame($booking->client_id, $note->client_id);
        $this->assertSame('121.00', (string) $note->amount);
        $this->assertSame('21.00', (string) $note->tax_rate);
        $this->assertSame('21.00', (string) $note->tax_amount); // 121 TTC @21% -> 21 de TVA
        $this->assertSame('re_test_1', $note->stripe_refund_id);
        $this->assertNotEmpty($note->credit_note_number);
        $this->assertNotNull($note->issued_at);
    }

    public function test_is_idempotent_by_stripe_refund_id(): void
    {
        $booking = $this->booking();

        $a = app(FinanceCreditNoteService::class)->createForRefund($booking, 5000, 're_dup', null);
        $b = app(FinanceCreditNoteService::class)->createForRefund($booking, 5000, 're_dup', null);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, FinanceCreditNote::count());
    }

    public function test_numbers_are_unique_and_sequential(): void
    {
        $n1 = app(FinanceCreditNoteService::class)->createForRefund($this->booking(), 1000, 're_a', null);
        $n2 = app(FinanceCreditNoteService::class)->createForRefund($this->booking(), 1000, 're_b', null);

        $this->assertNotSame($n1->credit_note_number, $n2->credit_note_number);
    }
}
