<?php

namespace Tests\Feature\Livewire\ClientCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ClientCompany\DisputesCenter;
use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Disputes\DisputeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Espace société B2B P2 — centre de litiges société : visibilité des litiges de
 * l'organisation (par site/booking), strictement scopé à l'org du membre.
 */
class CompanyDisputesCenterTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(OrganizationAccount $org): User
    {
        $user = User::factory()->client()->create([
            'current_organization_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    private function disputeForOrg(OrganizationAccount $org, User $member): ComplaintCase
    {
        $booking = Booking::factory()->create([
            'client_id' => $member->id,
            'customer_organization_id' => $org->id,
        ]);

        return app(DisputeService::class)->open($member, [
            'booking_id' => $booking->id,
            'subject' => 'Litige '.$org->id,
            'description' => 'Description du litige.',
            'category' => 'quality',
        ]);
    }

    public function test_lists_only_disputes_of_the_members_organization(): void
    {
        $orgA = OrganizationAccount::factory()->clientCompany()->create();
        $orgB = OrganizationAccount::factory()->clientCompany()->create();
        $memberA = $this->memberOf($orgA);

        $mine = $this->disputeForOrg($orgA, $memberA);
        $foreign = $this->disputeForOrg($orgB, $this->memberOf($orgB));

        $this->actingAs($memberA);

        Livewire::test(DisputesCenter::class)
            ->assertOk()
            ->assertViewHas('disputes', function ($disputes) use ($mine, $foreign) {
                $ids = collect($disputes->items())->pluck('id')->all();

                return in_array($mine->id, $ids, true) && ! in_array($foreign->id, $ids, true);
            });
    }

    public function test_member_opens_a_dispute_for_an_org_booking(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $member = $this->memberOf($org);
        $booking = Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'customer_organization_id' => $org->id,
        ]);

        $this->actingAs($member);

        Livewire::test(DisputesCenter::class)
            ->set('bookingId', (string) $booking->id)
            ->set('subject', 'Prestation incomplète')
            ->set('description', 'Le site B n’a pas été traité.')
            ->set('category', 'quality')
            ->call('openDispute');

        $this->assertDatabaseHas('complaint_cases', [
            'booking_id' => $booking->id,
            'organization_account_id' => $org->id,
            'subject' => 'Prestation incomplète',
        ]);
    }
}
