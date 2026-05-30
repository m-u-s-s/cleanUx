<?php

namespace Tests\Feature\Api\Client;

use App\Models\FinanceInvoice;
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
}
