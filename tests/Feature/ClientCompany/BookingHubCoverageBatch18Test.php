<?php

namespace Tests\Feature\ClientCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ClientCompany\BookingHub;
use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\ProviderProfile;
use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/** Coverage-focused exercise of App\Livewire\ClientCompany\BookingHub. */
class BookingHubCoverageBatch18Test extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /**
     * Owner org + user that can create/approve/cancel bookings.
     *
     * @return array{0: OrganizationAccount, 1: User}
     */
    private function makeOwner(bool $requiresApproval = false, string $role = OrganizationRole::OWNER->value): array
    {
        $org = OrganizationAccount::factory()->create([
            'requires_internal_approval' => $requiresApproval,
        ]);

        $user = User::factory()->entreprise()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'permissions' => null,
            'invited_by' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return [$org, $user];
    }

    private function makeSite(OrganizationAccount $org, array $overrides = []): OrganizationSite
    {
        return OrganizationSite::factory()->create(array_merge([
            'organization_account_id' => $org->id,
            'status' => 'active',
            'is_active' => true,
        ], $overrides));
    }

    #[Test]
    public function mount_with_site_param_opens_create_wizard_at_step_two(): void
    {
        [$org, $user] = $this->makeOwner();
        $site = $this->makeSite($org);

        Livewire::actingAs($user)
            ->test(BookingHub::class, ['site' => $site->id])
            ->assertOk()
            ->assertSet('view', 'create')
            ->assertSet('step', 2)
            ->assertSet('selectedSiteId', $site->id)
            ->assertSet('needsApproval', false);
    }

    #[Test]
    public function mount_flags_needs_approval_from_organization(): void
    {
        [, $user] = $this->makeOwner(requiresApproval: true);

        Livewire::actingAs($user)
            ->test(BookingHub::class)
            ->assertOk()
            ->assertSet('needsApproval', true);
    }

    #[Test]
    public function bookings_property_respects_status_and_site_filters(): void
    {
        [$org, $user] = $this->makeOwner();
        $siteA = $this->makeSite($org);
        $siteB = $this->makeSite($org);

        Booking::factory()->create([
            'customer_organization_id' => $org->id,
            'customer_user_id' => $user->id,
            'organization_site_id' => $siteA->id,
            'status' => 'pending',
        ]);
        Booking::factory()->create([
            'customer_organization_id' => $org->id,
            'customer_user_id' => $user->id,
            'organization_site_id' => $siteB->id,
            'status' => 'confirmed',
        ]);

        Livewire::actingAs($user)
            ->test(BookingHub::class)
            ->assertViewHas('bookings', fn (EloquentCollection $b) => $b->count() === 2)
            ->set('filterStatus', 'pending')
            ->assertViewHas('bookings', fn (EloquentCollection $b) => $b->count() === 1 && $b->first()->status === 'pending')
            ->set('filterStatus', '')
            ->set('filterSiteId', $siteB->id)
            ->assertViewHas('bookings', fn (EloquentCollection $b) => $b->count() === 1 && (int) $b->first()->organization_site_id === $siteB->id);
    }

    #[Test]
    public function sites_trades_and_selected_site_properties_are_org_scoped(): void
    {
        [$org, $user] = $this->makeOwner();
        $activeSite = $this->makeSite($org, ['name' => 'Active Site']);
        $this->makeSite($org, ['name' => 'Inactive Site', 'status' => 'inactive']);

        Trade::factory()->create(['is_active' => true]);
        Trade::factory()->create(['is_active' => false]);

        Livewire::actingAs($user)
            ->test(BookingHub::class)
            ->assertViewHas('sites', fn (EloquentCollection $s) => $s->count() === 1 && $s->first()->id === $activeSite->id)
            ->assertViewHas('trades', fn (EloquentCollection $t) => $t->count() === 1)
            ->assertViewHas('selectedSite', fn ($s) => $s === null)
            ->set('selectedSiteId', $activeSite->id)
            ->assertViewHas('selectedSite', fn (?OrganizationSite $s) => $s !== null && $s->id === $activeSite->id);
    }

    #[Test]
    public function available_providers_property_filters_by_trade_and_orders_preferred(): void
    {
        [$org, $user] = $this->makeOwner();

        $providerUser = User::factory()->employe()->create();
        ProviderProfile::create([
            'user_id' => $providerUser->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $trade = Trade::factory()->create(['is_active' => true]);
        $providerUser->trades()->attach($trade->id);

        $site = $this->makeSite($org, ['preferred_provider_id' => $providerUser->id]);

        Livewire::actingAs($user)
            ->test(BookingHub::class)
            // No site selected → empty collection branch.
            ->assertViewHas('providers', fn ($p) => $p->isEmpty())
            ->set('selectedSiteId', $site->id)
            ->set('selectedTradeId', $trade->id)
            ->assertViewHas('providers', fn ($p) => $p->count() === 1 && (int) $p->first()->user_id === $providerUser->id);
    }

    #[Test]
    public function wizard_navigation_select_and_step_guards(): void
    {
        [$org, $user] = $this->makeOwner();
        $site = $this->makeSite($org);
        $trade = Trade::factory()->create(['is_active' => true]);

        $component = Livewire::actingAs($user)
            ->test(BookingHub::class)
            ->assertOk();

        // selectSite → step 2.
        $component->call('selectSite', $site->id)
            ->assertSet('selectedSiteId', $site->id)
            ->assertSet('step', 2);

        // selectTrade → step 3.
        $component->call('selectTrade', $trade->id)
            ->assertSet('selectedTradeId', $trade->id)
            ->assertSet('step', 3);

        // prevStep walks back, clamped at 1.
        $component->call('prevStep')->assertSet('step', 2);
        $component->set('step', 1)->call('prevStep')->assertSet('step', 1);

        // nextStep: step 2 without a site → no-op (returns early).
        $component->set('step', 2)->set('selectedSiteId', null)
            ->call('nextStep')->assertSet('step', 2);

        // nextStep: step 3 without a trade → validation error.
        $component->set('step', 3)->set('selectedTradeId', null)
            ->call('nextStep')
            ->assertHasErrors('selectedTradeId')
            ->assertSet('step', 3);

        // nextStep: step 4 with a blank date → validation error.
        $component->set('step', 4)->set('scheduledDate', '')
            ->call('nextStep')
            ->assertHasErrors('scheduledDate')
            ->assertSet('step', 4);

        // nextStep: happy increment.
        $component->set('step', 1)
            ->call('nextStep')
            ->assertSet('step', 2);
    }

    #[Test]
    public function submit_booking_without_catalog_adds_trade_error(): void
    {
        [$org, $user] = $this->makeOwner();
        $site = $this->makeSite($org);
        $trade = Trade::factory()->create(['is_active' => true]); // no ServiceCatalog attached

        Livewire::actingAs($user)
            ->test(BookingHub::class)
            ->set('selectedSiteId', $site->id)
            ->set('selectedTradeId', $trade->id)
            ->set('scheduledDate', now()->addDays(3)->toDateString())
            ->set('scheduledTime', '10:00')
            ->call('submitBooking')
            ->assertHasErrors('selectedTradeId');

        $this->assertDatabaseMissing('bookings', ['customer_organization_id' => $org->id]);
    }

    #[Test]
    public function submit_booking_on_uncovered_site_adds_site_error(): void
    {
        [$org, $user] = $this->makeOwner();
        $trade = Trade::factory()->create(['is_active' => true]);

        // An active catalog exists for the trade (passes the catalog guard) but the
        // site address resolves to no zone/postal coverage.
        ServiceCatalog::factory()->create([
            'trade_id' => $trade->id,
            'is_active' => true,
            'service_type' => 'nettoyage_standard',
        ]);

        $site = $this->makeSite($org, [
            'postal_code' => '00000',
            'city' => 'Nulle Part',
            'postal_code_id' => null,
        ]);

        Livewire::actingAs($user)
            ->test(BookingHub::class)
            ->set('selectedSiteId', $site->id)
            ->set('selectedTradeId', $trade->id)
            ->set('scheduledDate', now()->addDays(3)->toDateString())
            ->set('scheduledTime', '10:00')
            ->call('submitBooking')
            ->assertHasErrors('selectedSiteId');

        $this->assertDatabaseMissing('bookings', ['customer_organization_id' => $org->id]);
    }

    #[Test]
    public function submit_booking_without_available_employee_adds_time_error(): void
    {
        $context = $this->createCoverageContext();

        $trade = Trade::factory()->create(['is_active' => true]);
        $context['service']->forceFill(['trade_id' => $trade->id])->save();

        [$org, $user] = $this->makeOwner();

        $site = OrganizationSite::create([
            'organization_account_id' => $org->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'name' => 'Siège',
            'address_line_1' => '1 rue du Site',
            'city' => $context['postalCode']->city_name,
            'postal_code' => $context['postalCode']->code,
            'status' => 'active',
            'is_active' => true,
        ]);

        // No employee assigned to the zone → resolveBestAvailableEmployeeForSlot null.
        Livewire::actingAs($user)
            ->test(BookingHub::class)
            ->set('selectedSiteId', $site->id)
            ->set('selectedTradeId', $trade->id)
            ->set('scheduledDate', now()->addDays(2)->toDateString())
            ->set('scheduledTime', '10:00')
            ->call('submitBooking')
            ->assertHasErrors('scheduledTime');

        $this->assertDatabaseMissing('bookings', ['customer_organization_id' => $org->id]);
    }

    #[Test]
    public function approve_booking_is_forbidden_for_a_requester(): void
    {
        [$org, $requester] = $this->makeOwner(role: OrganizationRole::REQUESTER->value);
        $site = $this->makeSite($org);

        $booking = Booking::factory()->create([
            'customer_organization_id' => $org->id,
            'customer_user_id' => $requester->id,
            'organization_site_id' => $site->id,
            'status' => 'pending_approval',
        ]);

        Livewire::actingAs($requester)
            ->test(BookingHub::class)
            ->call('approveBooking', $booking->id)
            ->assertForbidden();
    }

    #[Test]
    public function cancel_booking_is_forbidden_for_a_requester(): void
    {
        [$org, $requester] = $this->makeOwner(role: OrganizationRole::REQUESTER->value);
        $site = $this->makeSite($org);

        $booking = Booking::factory()->create([
            'customer_organization_id' => $org->id,
            'customer_user_id' => $requester->id,
            'organization_site_id' => $site->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($requester)
            ->test(BookingHub::class)
            ->call('cancelBooking', $booking->id)
            ->assertForbidden();
    }

    #[Test]
    public function cancel_booking_marks_status_cancelled_for_an_owner(): void
    {
        [$org, $user] = $this->makeOwner();
        $site = $this->makeSite($org);

        $booking = Booking::factory()->create([
            'customer_organization_id' => $org->id,
            'customer_user_id' => $user->id,
            'organization_site_id' => $site->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($user)
            ->test(BookingHub::class)
            ->call('cancelBooking', $booking->id)
            ->assertHasNoErrors();

        $this->assertSame('cancelled', $booking->refresh()->status);
    }
}
