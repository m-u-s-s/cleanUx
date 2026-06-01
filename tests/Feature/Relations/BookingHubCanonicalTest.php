<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationRole;
use App\Livewire\ClientCompany\BookingHub;
use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * SP2 Task 8 — le portail société cliente (BookingHub) doit router via
 * CreateBookingAction (action canonique) au lieu d'un Booking::create() ad-hoc.
 *
 * Bug central corrigé : l'ancien chemin PERDAIT le service_catalog_id (jamais
 * posé) et ne générait ni zone_snapshot ni pricing_snapshot. Ces tests prouvent
 * que la réservation passe désormais par l'action canonique.
 */
class BookingHubCanonicalTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /**
     * Monte un contexte société complet : org account + membre owner (permissions),
     * site rattaché à la zone/code postal couverts, métier (Trade) lié au
     * service_catalog, et un employé éligible (provider profile vérifié +
     * affectation zone + disponibilité) pour le créneau visé.
     *
     * @return array{user: User, org: OrganizationAccount, site: OrganizationSite, trade: Trade, employee: User, context: array<string, mixed>, date: string, time: string}
     */
    private function makeCompanyBookingContext(): array
    {
        $context = $this->createCoverageContext();

        // Métier rattaché au service_catalog (BookingHub raisonne en trade_id).
        $trade = Trade::factory()->create(['is_active' => true]);
        $context['service']->forceFill(['trade_id' => $trade->id])->save();

        $org = OrganizationAccount::factory()->create([
            'requires_internal_approval' => false,
        ]);

        $user = User::factory()->entreprise()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'permissions' => null,
            'invited_by' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

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

        // Créneau couvert par la disponibilité créée ci-dessous.
        $date = now()->addDays(2)->toDateString();
        $time = '10:00';

        $employee = User::factory()->employe()->create([
            'is_active' => true,
            'primary_service_zone_id' => $context['zone']->id,
        ]);

        $this->assignEmployeeToZone(
            $employee,
            $context['zone'],
            [],
            ['date' => $date, 'heure_debut' => '08:00:00', 'heure_fin' => '20:00:00'],
        );

        return compact('user', 'org', 'site', 'trade', 'employee', 'context', 'date', 'time');
    }

    #[Test]
    public function booking_hub_routes_through_canonical_action_and_sets_service_catalog(): void
    {
        $ctx = $this->makeCompanyBookingContext();

        $this->actingAs($ctx['user']);

        Livewire::test(BookingHub::class)
            ->set('selectedSiteId', $ctx['site']->id)
            ->set('selectedTradeId', $ctx['trade']->id)
            ->set('scheduledDate', $ctx['date'])
            ->set('scheduledTime', $ctx['time'])
            ->call('submitBooking')
            ->assertHasNoErrors();

        $booking = Booking::query()
            ->where('customer_organization_id', $ctx['org']->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($booking, 'Une réservation société doit avoir été créée.');

        // Bug central : le service_catalog_id (du métier) DOIT être posé.
        $this->assertNotNull(
            $booking->service_catalog_id,
            'service_catalog_id doit être renseigné (bug audit : il était perdu).'
        );
        $this->assertSame($ctx['context']['service']->id, $booking->service_catalog_id);

        // Contexte société préservé.
        $this->assertSame($ctx['org']->id, (int) $booking->customer_organization_id);
        $this->assertSame($ctx['site']->id, (int) $booking->organization_site_id);

        // Preuve du passage par CreateBookingAction : snapshots générés.
        $this->assertNotNull($booking->zone_snapshot, 'zone_snapshot doit être généré par l’action canonique.');
        $this->assertNotNull($booking->pricing_snapshot, 'pricing_snapshot doit être généré par l’action canonique.');
        $this->assertSame($ctx['context']['zone']->id, (int) $booking->service_zone_id);
    }

    /**
     * Variante du contexte société : l'organisation REQUIERT une approbation interne
     * et le soumetteur est un REQUESTER (peut `bookings.create` mais PAS
     * `bookings.approve`). Un OWNER approbateur est aussi rattaché à l'org.
     *
     * @return array{requester: User, approver: User, org: OrganizationAccount, site: OrganizationSite, trade: Trade, employee: User, context: array<string, mixed>, date: string, time: string}
     */
    private function makeApprovalRequiredContext(): array
    {
        $context = $this->createCoverageContext();

        $trade = Trade::factory()->create(['is_active' => true]);
        $context['service']->forceFill(['trade_id' => $trade->id])->save();

        $org = OrganizationAccount::factory()->create([
            'requires_internal_approval' => true,
        ]);

        // Soumetteur : REQUESTER — peut créer mais NE PEUT PAS approuver.
        $requester = User::factory()->entreprise()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $requester->id,
            'role' => OrganizationRole::REQUESTER->value,
            'status' => 'active',
            'permissions' => null,
            'invited_by' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        // Approbateur : OWNER — peut approuver.
        $approver = User::factory()->entreprise()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $approver->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'permissions' => null,
            'invited_by' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

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

        $date = now()->addDays(2)->toDateString();
        $time = '10:00';

        $employee = User::factory()->employe()->create([
            'is_active' => true,
            'primary_service_zone_id' => $context['zone']->id,
        ]);

        $this->assignEmployeeToZone(
            $employee,
            $context['zone'],
            [],
            ['date' => $date, 'heure_debut' => '08:00:00', 'heure_fin' => '20:00:00'],
        );

        return compact('requester', 'approver', 'org', 'site', 'trade', 'employee', 'context', 'date', 'time');
    }

    #[Test]
    public function approval_required_booking_is_created_as_pending_approval_then_approver_moves_it_to_pending(): void
    {
        $ctx = $this->makeApprovalRequiredContext();

        // 1) Le REQUESTER (ne peut PAS approuver) soumet → status pending_approval.
        $this->actingAs($ctx['requester']);

        Livewire::test(BookingHub::class)
            ->set('selectedSiteId', $ctx['site']->id)
            ->set('selectedTradeId', $ctx['trade']->id)
            ->set('scheduledDate', $ctx['date'])
            ->set('scheduledTime', $ctx['time'])
            ->call('submitBooking')
            ->assertHasNoErrors();

        $booking = Booking::query()
            ->where('customer_organization_id', $ctx['org']->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($booking, 'Une réservation société doit avoir été créée.');

        // Vocabulaire du hub : la booking doit être approuvable (vue + approveBooking).
        $this->assertSame(
            'pending_approval',
            $booking->status,
            'Une booking nécessitant approbation doit être créée en pending_approval (sinon le bouton Approuver ne s’affiche jamais).'
        );
        // Preuve durable du circuit d'approbation : un EnterpriseBookingApproval existe.
        $this->assertDatabaseHas('enterprise_booking_approvals', [
            'rendez_vous_id' => $booking->id,
        ]);

        // 2) Un approbateur (OWNER) approuve → transition pending_approval → pending.
        $this->actingAs($ctx['approver']);

        Livewire::test(BookingHub::class)
            ->call('approveBooking', $booking->id)
            ->assertHasNoErrors();

        $booking->refresh();

        $this->assertSame(
            'pending',
            $booking->status,
            'approveBooking doit faire transiter la booking vers pending (en attente prestataire).'
        );
    }

    #[Test]
    public function booking_without_approval_required_is_created_as_pending(): void
    {
        $ctx = $this->makeCompanyBookingContext();

        $this->actingAs($ctx['user']);

        Livewire::test(BookingHub::class)
            ->set('selectedSiteId', $ctx['site']->id)
            ->set('selectedTradeId', $ctx['trade']->id)
            ->set('scheduledDate', $ctx['date'])
            ->set('scheduledTime', $ctx['time'])
            ->call('submitBooking')
            ->assertHasNoErrors();

        $booking = Booking::query()
            ->where('customer_organization_id', $ctx['org']->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($booking);
        $this->assertSame(
            'pending',
            $booking->status,
            'Sans approbation requise, la booking doit être créée en pending.'
        );
    }

    #[Test]
    public function booking_hub_resets_wizard_and_dispatches_event(): void
    {
        $ctx = $this->makeCompanyBookingContext();

        $this->actingAs($ctx['user']);

        Livewire::test(BookingHub::class)
            ->set('selectedSiteId', $ctx['site']->id)
            ->set('selectedTradeId', $ctx['trade']->id)
            ->set('scheduledDate', $ctx['date'])
            ->set('scheduledTime', $ctx['time'])
            ->call('submitBooking')
            ->assertHasNoErrors()
            ->assertDispatched('booking-created')
            ->assertSet('view', 'list')
            ->assertSet('step', 1)
            ->assertSet('selectedSiteId', null);
    }
}
