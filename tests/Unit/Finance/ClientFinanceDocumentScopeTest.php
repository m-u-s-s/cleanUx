<?php

namespace Tests\Unit\Finance;

use App\Models\FinanceInvoice;
use App\Models\User;
use App\Support\Finance\ClientFinanceDocumentScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientFinanceDocumentScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scopes_to_the_users_own_invoices_only(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $other = User::factory()->create(['role' => 'client']);
        $mine = $this->makeInvoice(['client_id' => $me->id]);
        $theirs = $this->makeInvoice(['client_id' => $other->id]);

        $ids = ClientFinanceDocumentScope::apply(FinanceInvoice::query(), $me)->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id), 'must not include another client\'s invoice');
    }

    public function test_unauthenticated_user_sees_nothing(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $this->makeInvoice(['client_id' => $user->id]);

        // Build a stub that has no ID (simulates a null guard scenario handled upstream)
        // The static apply() method requires a real User; the null guard lives in the
        // Livewire component. Here we verify the zero-row path via an ID that matches nothing.
        $ghost = new User(['id' => 0]);
        $ghost->id = 0;

        $ids = ClientFinanceDocumentScope::apply(FinanceInvoice::query(), $ghost)->pluck('id');

        $this->assertCount(0, $ids, 'a ghost/anonymous user must see no invoices');
    }

    public function test_enterprise_user_without_org_sees_only_own_invoices(): void
    {
        $me = User::factory()->create([
            'role' => 'client',
            'organization_account_id' => null,
        ]);
        $mine = $this->makeInvoice(['client_id' => $me->id]);
        $other = User::factory()->create(['role' => 'client']);
        $theirs = $this->makeInvoice(['client_id' => $other->id]);

        $ids = ClientFinanceDocumentScope::apply(FinanceInvoice::query(), $me)->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    private function makeInvoice(array $attrs): FinanceInvoice
    {
        // FinanceInvoice has a factory — use it.
        return FinanceInvoice::factory()->create($attrs);
    }
}
