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
}
