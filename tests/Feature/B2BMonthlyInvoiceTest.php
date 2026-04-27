<?php

namespace Tests\Feature;

use App\Models\OrganizationAccount;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\Finance\B2BMonthlyInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2BMonthlyInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_monthly_invoice_for_b2b_organization(): void
    {
        $organization = OrganizationAccount::factory()->create([
            'status' => 'active',
            'metadata' => [
                'tax_rate' => 21,
                'currency' => 'EUR',
                'payment_terms_days' => 30,
            ],
        ]);

        $client = User::factory()->create([
            'role' => 'entreprise',
            'is_active' => true,
            'organization_account_id' => $organization->id,
        ]);

        RendezVous::factory()->count(2)->create([
            'client_id' => $client->id,
            'organization_account_id' => $organization->id,
            'status' => 'termine',
            'date' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
            'devis_estime' => 100,
        ]);

        $invoice = app(B2BMonthlyInvoiceService::class)->generateForOrganization(
            $organization,
            now()->subMonth()->startOfMonth(),
            now()->subMonth()->endOfMonth()
        );

        $this->assertNotNull($invoice);
        $this->assertEquals('b2b_monthly', $invoice->invoice_type);
        $this->assertEquals(200, (float) $invoice->subtotal);
        $this->assertEquals(242, (float) $invoice->total_amount);
    }

    public function test_it_returns_null_when_no_rendez_vous_are_billable(): void
    {
        $organization = OrganizationAccount::factory()->create([
            'status' => 'active',
        ]);

        $invoice = app(B2BMonthlyInvoiceService::class)->generateForOrganization(
            $organization,
            now()->subMonth()->startOfMonth(),
            now()->subMonth()->endOfMonth()
        );

        $this->assertNull($invoice);
    }
}