<?php

namespace Tests\Feature\Finance;

use App\Models\FinanceInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Durcissement — les PDF finance ne sont accessibles que via une URL signée expirante (en plus de l'auth + ownership déjà en place). */
class FinanceSignedDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceFor(User $owner): FinanceInvoice
    {
        return FinanceInvoice::factory()->create([
            'client_id' => $owner->id,
            'organization_account_id' => null,
        ]);
    }

    private function signedUrl(FinanceInvoice $invoice, $expiry): string
    {
        return URL::temporarySignedRoute('client.finance.invoice.download', $expiry, ['invoice' => $invoice->id]);
    }

    public function test_unsigned_download_is_rejected(): void
    {
        $owner = User::factory()->client()->create();
        $invoice = $this->invoiceFor($owner);

        // route() => URL sans signature.
        $this->actingAs($owner)
            ->get(route('client.finance.invoice.download', $invoice))
            ->assertForbidden();
    }

    public function test_valid_signed_url_lets_the_owner_download(): void
    {
        $owner = User::factory()->client()->create();
        $invoice = $this->invoiceFor($owner);

        $this->actingAs($owner)
            ->get($this->signedUrl($invoice, now()->addMinutes(30)))
            ->assertOk();
    }

    public function test_signed_url_still_enforces_ownership(): void
    {
        $owner = User::factory()->client()->create();
        $invoice = $this->invoiceFor($owner);
        $intruder = User::factory()->client()->create();

        // URL signée valide, mais l'intrus n'est pas le propriétaire.
        $this->actingAs($intruder)
            ->get($this->signedUrl($invoice, now()->addMinutes(30)))
            ->assertForbidden();
    }

    public function test_expired_signed_url_is_rejected(): void
    {
        $owner = User::factory()->client()->create();
        $invoice = $this->invoiceFor($owner);

        $url = $this->signedUrl($invoice, now()->subMinute());

        $this->actingAs($owner)->get($url)->assertForbidden();
    }
}
