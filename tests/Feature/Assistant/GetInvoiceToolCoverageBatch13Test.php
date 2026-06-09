<?php

namespace Tests\Feature\Assistant;

use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Models\User;
use App\Services\Assistant\Tools\Implementations\GetInvoiceTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetInvoiceToolCoverageBatch13Test extends TestCase
{
    use RefreshDatabase;

    private function tool(): GetInvoiceTool
    {
        return new GetInvoiceTool;
    }

    private function actor(): User
    {
        return User::factory()->create(['role' => 'client']);
    }

    public function test_metadata_schema_and_flags(): void
    {
        $tool = $this->tool();

        $this->assertSame('get_invoice', $tool->name());
        $this->assertStringContainsString('facture', strtolower($tool->description()));
        $this->assertTrue($tool->executesImmediately());
        $this->assertTrue($tool->authorize($this->actor()));

        $schema = $tool->inputSchema();
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('invoice_id', $schema['properties']);
        $this->assertArrayHasKey('invoice_number', $schema['properties']);
        $this->assertSame([], $schema['required']);
    }

    public function test_execute_returns_invoice_by_id_for_owner(): void
    {
        $owner = $this->actor();
        $invoice = FinanceInvoice::factory()->create([
            'client_id' => $owner->id,
            'invoice_number' => 'INV-OWN-0001',
            'subtotal' => 100.00,
            'tax_amount' => 21.00,
            'total_amount' => 121.00,
            'balance_due' => 121.00,
            'paid_at' => null,
            'due_at' => now()->addDays(10),
        ]);

        $result = $this->tool()->execute($owner, ['invoice_id' => $invoice->id]);

        $this->assertTrue($result['ok']);
        $row = $result['invoice'];
        $this->assertSame($invoice->id, $row['id']);
        $this->assertSame('INV-OWN-0001', $row['invoice_number']);
        $this->assertSame('EUR', $row['currency']);
        $this->assertSame(121.00, $row['total_amount']);
        $this->assertFalse($row['is_paid']);
        $this->assertFalse($row['is_overdue']);
        $this->assertNotNull($row['issued_at']);
        $this->assertNotNull($row['due_at']);
        $this->assertNull($row['paid_at']);
    }

    public function test_execute_looks_up_by_invoice_number(): void
    {
        $owner = $this->actor();
        $invoice = FinanceInvoice::factory()->paid()->create([
            'client_id' => $owner->id,
            'invoice_number' => 'INV-NUM-7777',
            'due_at' => now()->subDays(5),
        ]);

        $result = $this->tool()->execute($owner, ['invoice_number' => 'INV-NUM-7777']);

        $this->assertTrue($result['ok']);
        $this->assertSame($invoice->id, $result['invoice']['id']);
        $this->assertTrue($result['invoice']['is_paid']);
        // Paid invoice past due date is not overdue.
        $this->assertFalse($result['invoice']['is_overdue']);
        $this->assertNotNull($result['invoice']['paid_at']);
    }

    public function test_overdue_unpaid_invoice_is_flagged(): void
    {
        $owner = $this->actor();
        $invoice = FinanceInvoice::factory()->create([
            'client_id' => $owner->id,
            'paid_at' => null,
            'due_at' => now()->subDays(3),
        ]);

        $result = $this->tool()->execute($owner, ['invoice_id' => $invoice->id]);

        $this->assertTrue($result['invoice']['is_overdue']);
        $this->assertFalse($result['invoice']['is_paid']);
    }

    public function test_invoice_not_found_returns_error(): void
    {
        $result = $this->tool()->execute($this->actor(), ['invoice_id' => 999999]);

        $this->assertFalse($result['ok']);
        $this->assertSame('Facture introuvable.', $result['error']);
    }

    public function test_non_owner_without_org_is_denied(): void
    {
        $owner = $this->actor();
        $stranger = $this->actor();

        $invoice = FinanceInvoice::factory()->create([
            'client_id' => $owner->id,
        ]);

        $result = $this->tool()->execute($stranger, ['invoice_id' => $invoice->id]);

        $this->assertFalse($result['ok']);
        $this->assertSame("Vous n'avez pas accès à cette facture.", $result['error']);
    }

    public function test_org_member_can_access_org_invoice(): void
    {
        $org = OrganizationAccount::factory()->create();
        $member = User::factory()->create([
            'role' => 'entreprise',
            'organization_account_id' => $org->id,
        ]);
        $billedClient = $this->actor();

        $invoice = FinanceInvoice::factory()->create([
            'client_id' => $billedClient->id,
            'organization_account_id' => $org->id,
        ]);

        $result = $this->tool()->execute($member, ['invoice_id' => $invoice->id]);

        $this->assertTrue($result['ok']);
        $this->assertSame($invoice->id, $result['invoice']['id']);
    }

    public function test_no_identifier_lists_latest_for_solo_user(): void
    {
        $owner = $this->actor();
        $other = $this->actor();

        FinanceInvoice::factory()->count(7)->create(['client_id' => $owner->id]);
        FinanceInvoice::factory()->create(['client_id' => $other->id]);

        $result = $this->tool()->execute($owner, []);

        $this->assertTrue($result['ok']);
        // Capped at 5 latest.
        $this->assertSame(5, $result['count']);
        $this->assertCount(5, $result['invoices']);
    }

    public function test_no_identifier_lists_latest_for_org_user(): void
    {
        $org = OrganizationAccount::factory()->create();
        $member = User::factory()->create([
            'role' => 'entreprise',
            'organization_account_id' => $org->id,
        ]);

        FinanceInvoice::factory()->create(['client_id' => $member->id]);
        FinanceInvoice::factory()->create([
            'client_id' => $this->actor()->id,
            'organization_account_id' => $org->id,
        ]);
        // Unrelated invoice should be excluded.
        FinanceInvoice::factory()->create(['client_id' => $this->actor()->id]);

        $result = $this->tool()->execute($member, []);

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['count']);
    }
}
