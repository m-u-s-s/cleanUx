<?php

namespace Tests\Unit\Finance;

use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\User;
use App\Support\Finance\ClientFinanceDocumentScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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
        // L'identifiant est posé directement : le passer au constructeur l'assignerait en masse,
        // et la ligne suivante le fixait déjà.
        $ghost = new User;
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

    // -------------------------------------------------------------------------
    // Enterprise org/site-scope branch tests
    // -------------------------------------------------------------------------

    /**
     * An org-A user must see invoices belonging to org A (via organization_account_id)
     * and must NOT see invoices belonging to org B, even when those org-B invoices
     * happen to share client_id=null (so only the org path could ever match).
     */
    #[Test]
    public function test_org_user_sees_own_org_invoices_not_another_org(): void
    {
        // Two separate orgs.
        $orgA = OrganizationAccount::factory()->create();
        $orgB = OrganizationAccount::factory()->create();

        // The user belongs to org A.  site_scope='all' so no site filtering applies.
        $userA = User::factory()->create([
            'role' => 'client',
            'organization_account_id' => $orgA->id,
            'metadata' => ['entreprise_context' => ['site_scope' => 'all', 'allowed_site_ids' => []]],
        ]);

        // A third "other" client whose client_id the org invoices will NOT match.
        $otherClient = User::factory()->create(['role' => 'client']);

        // Org-A invoice: organization_account_id=orgA, client_id=otherClient
        // (not the user's own client_id → only the org branch can include it).
        $invoiceOrgA = $this->makeInvoice([
            'organization_account_id' => $orgA->id,
            'client_id' => $otherClient->id,
        ]);

        // Org-B invoice: organization_account_id=orgB — must be excluded.
        $invoiceOrgB = $this->makeInvoice([
            'organization_account_id' => $orgB->id,
            'client_id' => $otherClient->id,
        ]);

        $ids = ClientFinanceDocumentScope::apply(FinanceInvoice::query(), $userA)->pluck('id');

        $this->assertTrue(
            $ids->contains($invoiceOrgA->id),
            'org-A user must see an org-A invoice (org branch included)'
        );
        $this->assertFalse(
            $ids->contains($invoiceOrgB->id),
            'org-A user must NOT see an org-B invoice (cross-tenant leak)'
        );
    }

    /**
     * When site_scope='selected' and allowed_site_ids=[site1], an org invoice
     * attached to a booking at site1 is visible, but one at site2 is excluded.
     *
     * The invoices share the same organization_account_id and a client_id that
     * is NOT the user, so the only path that can match is the org+site branch.
     */
    #[Test]
    public function test_site_scope_selected_filters_to_allowed_sites(): void
    {
        $org = OrganizationAccount::factory()->create();

        $site1 = OrganizationSite::factory()->create(['organization_account_id' => $org->id]);
        $site2 = OrganizationSite::factory()->create(['organization_account_id' => $org->id]);

        // Org user: site_scope='selected', allowed only site1.
        $user = User::factory()->create([
            'role' => 'client',
            'organization_account_id' => $org->id,
            'metadata' => [
                'entreprise_context' => [
                    'site_scope' => 'selected',
                    'allowed_site_ids' => [$site1->id],
                ],
            ],
        ]);

        // "Foreign" client so client_id never matches the user directly.
        $foreignClient = User::factory()->create(['role' => 'client']);

        // Booking at site1.
        $bookingSite1 = Booking::factory()->create([
            'client_id' => $foreignClient->id,
            'organization_account_id' => $org->id,
            'organization_site_id' => $site1->id,
        ]);

        // Booking at site2.
        $bookingSite2 = Booking::factory()->create([
            'client_id' => $foreignClient->id,
            'organization_account_id' => $org->id,
            'organization_site_id' => $site2->id,
        ]);

        // Invoice for each booking, attached to the same org.
        $invoiceSite1 = $this->makeInvoice([
            'organization_account_id' => $org->id,
            'client_id' => $foreignClient->id,
            'rendez_vous_id' => $bookingSite1->id,
        ]);

        $invoiceSite2 = $this->makeInvoice([
            'organization_account_id' => $org->id,
            'client_id' => $foreignClient->id,
            'rendez_vous_id' => $bookingSite2->id,
        ]);

        $ids = ClientFinanceDocumentScope::apply(FinanceInvoice::query(), $user)->pluck('id');

        $this->assertTrue(
            $ids->contains($invoiceSite1->id),
            'invoice linked to allowed site1 must be included'
        );
        $this->assertFalse(
            $ids->contains($invoiceSite2->id),
            'invoice linked to site2 (not in allowed_site_ids) must be excluded'
        );
    }

    /**
     * When site_scope='selected' and allowed_site_ids=[], the org path collapses
     * to whereRaw('1 = 0'), so no org invoices are returned even though the org
     * matches — preventing a "see everything" fallback on misconfigured accounts.
     */
    #[Test]
    public function test_site_scope_selected_with_empty_allowed_sites_sees_no_org_invoices(): void
    {
        $org = OrganizationAccount::factory()->create();
        $site = OrganizationSite::factory()->create(['organization_account_id' => $org->id]);

        // Org user with site_scope='selected' but no sites granted.
        $user = User::factory()->create([
            'role' => 'client',
            'organization_account_id' => $org->id,
            'metadata' => [
                'entreprise_context' => [
                    'site_scope' => 'selected',
                    'allowed_site_ids' => [],
                ],
            ],
        ]);

        $foreignClient = User::factory()->create(['role' => 'client']);

        $booking = Booking::factory()->create([
            'client_id' => $foreignClient->id,
            'organization_account_id' => $org->id,
            'organization_site_id' => $site->id,
        ]);

        $orgInvoice = $this->makeInvoice([
            'organization_account_id' => $org->id,
            'client_id' => $foreignClient->id,
            'rendez_vous_id' => $booking->id,
        ]);

        $ids = ClientFinanceDocumentScope::apply(FinanceInvoice::query(), $user)->pluck('id');

        $this->assertFalse(
            $ids->contains($orgInvoice->id),
            'empty allowed_site_ids must yield zero org invoices (whereRaw 1=0 path)'
        );
    }

    private function makeInvoice(array $attrs): FinanceInvoice
    {
        // FinanceInvoice has a factory — use it.
        return FinanceInvoice::factory()->create($attrs);
    }
}
