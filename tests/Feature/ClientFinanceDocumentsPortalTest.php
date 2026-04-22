<?php

namespace Tests\Feature;

use App\Models\FinanceInvoice;
use App\Models\FinanceQuote;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientFinanceDocumentsPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_sees_only_his_finance_documents(): void
    {
        $client = User::factory()->client()->create([
            'plan_type' => 'premium',
            'plan_status' => 'active',
            'premium_renewal_at' => now()->addMonth(),
        ]);
        $other = User::factory()->client()->create();

        $mine = RendezVous::factory()->for($client, 'client')->create(['status' => 'confirme']);
        $otherRdv = RendezVous::factory()->for($other, 'client')->create(['status' => 'confirme']);

        $myQuote = FinanceQuote::create([
            'rendez_vous_id' => $mine->id,
            'client_id' => $client->id,
            'quote_number' => 'DEV-CLIENT-001',
            'status' => 'sent',
            'subtotal' => 100,
            'tax_rate' => 21,
            'tax_amount' => 21,
            'total_amount' => 121,
            'issued_at' => now(),
            'valid_until' => now()->addDays(15),
        ]);

        $myInvoice = FinanceInvoice::create([
            'rendez_vous_id' => $mine->id,
            'finance_quote_id' => $myQuote->id,
            'client_id' => $client->id,
            'invoice_number' => 'FAC-CLIENT-001',
            'status' => 'partial',
            'subtotal' => 100,
            'tax_rate' => 21,
            'tax_amount' => 21,
            'total_amount' => 121,
            'balance_due' => 61,
            'issued_at' => now(),
            'due_at' => now()->addDays(10),
        ]);

        FinanceQuote::create([
            'rendez_vous_id' => $otherRdv->id,
            'client_id' => $other->id,
            'quote_number' => 'DEV-CLIENT-999',
            'status' => 'sent',
            'subtotal' => 100,
            'tax_rate' => 21,
            'tax_amount' => 21,
            'total_amount' => 121,
            'issued_at' => now(),
            'valid_until' => now()->addDays(15),
        ]);

        FinanceInvoice::create([
            'rendez_vous_id' => $otherRdv->id,
            'client_id' => $other->id,
            'invoice_number' => 'FAC-CLIENT-999',
            'status' => 'issued',
            'subtotal' => 100,
            'tax_rate' => 21,
            'tax_amount' => 21,
            'total_amount' => 121,
            'balance_due' => 121,
            'issued_at' => now(),
            'due_at' => now()->addDays(10),
        ]);

        $this->actingAs($client)
            ->get(route('client.finance'))
            ->assertOk()
            ->assertSee('DEV-CLIENT-001')
            ->assertSee('FAC-CLIENT-001')
            ->assertSee('Documents & finance')
            ->assertSee('Actif')
            ->assertDontSee('DEV-CLIENT-999')
            ->assertDontSee('FAC-CLIENT-999');

        $this->actingAs($client)
            ->get(route('client.finance.quote.download', $myQuote))
            ->assertOk();

        $this->actingAs($client)
            ->get(route('client.finance.invoice.download', $myInvoice))
            ->assertOk();
    }

    public function test_client_cannot_download_other_client_documents(): void
    {
        $client = User::factory()->client()->create();
        $other = User::factory()->client()->create();

        $otherRdv = RendezVous::factory()->for($other, 'client')->create(['status' => 'confirme']);
        $otherQuote = FinanceQuote::create([
            'rendez_vous_id' => $otherRdv->id,
            'client_id' => $other->id,
            'quote_number' => 'DEV-OTHER-001',
            'status' => 'sent',
            'subtotal' => 100,
            'tax_rate' => 21,
            'tax_amount' => 21,
            'total_amount' => 121,
            'issued_at' => now(),
            'valid_until' => now()->addDays(15),
        ]);
        $otherInvoice = FinanceInvoice::create([
            'rendez_vous_id' => $otherRdv->id,
            'finance_quote_id' => $otherQuote->id,
            'client_id' => $other->id,
            'invoice_number' => 'FAC-OTHER-001',
            'status' => 'issued',
            'subtotal' => 100,
            'tax_rate' => 21,
            'tax_amount' => 21,
            'total_amount' => 121,
            'balance_due' => 121,
            'issued_at' => now(),
            'due_at' => now()->addDays(10),
        ]);

        $this->actingAs($client)
            ->get(route('client.finance.quote.download', $otherQuote))
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('client.finance.invoice.download', $otherInvoice))
            ->assertForbidden();
    }
}
