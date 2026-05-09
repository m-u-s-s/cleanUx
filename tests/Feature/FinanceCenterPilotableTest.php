<?php

namespace Tests\Feature;

use App\Livewire\Admin\FinanceCenter;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class FinanceCenterPilotableTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    public function test_admin_can_sync_and_partially_pay_invoice_from_finance_center(): void
    {
        $context = $this->createCoverageContext();
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        $employee = User::factory()->employe()->create();

        $rdv = Booking::factory()->confirme()->create([
            'client_id' => $client->id,
            'employe_id' => $employee->id,
            'service_catalog_id' => $context['service']->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'devis_estime' => 100,
            'date' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAs($admin);

        Livewire::test(FinanceCenter::class)
            ->call('ensureInvoiceDocument', $rdv->id)
            ->set('manualPaymentAmount', '60')
            ->set('manualPaymentMethod', 'bank_transfer')
            ->call('recordPartialPaymentNow', $rdv->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('finance_invoices', [
            'rendez_vous_id' => $rdv->id,
        ]);

        $invoice = $rdv->fresh()->financeInvoice()->firstOrFail();
        $this->assertSame('partial', $invoice->status);
        $this->assertEquals(67.05, round((float) $invoice->balance_due, 2));
    }
}
