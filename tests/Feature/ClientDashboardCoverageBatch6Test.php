<?php

namespace Tests\Feature;

use App\Livewire\ClientDashboard;
use App\Models\Booking;
use App\Models\Feedback;
use App\Models\FinanceInvoice;
use App\Models\FinanceQuote;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientDashboardCoverageBatch6Test extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_and_exposes_booking_centric_properties_for_a_standard_client(): void
    {
        $client = User::factory()->client()->create();

        $future = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => BookingStatus::CONFIRME,
            'date' => now()->addDays(3)->toDateString(),
            'heure' => '09:00:00',
            'adresse' => 'Rue Avenir 10',
            'ville' => 'Bruxelles',
            'code_postal' => '1000',
        ]);

        $past = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => BookingStatus::TERMINE,
            'date' => now()->subDays(5)->toDateString(),
            'heure' => '14:00:00',
            'adresse' => 'Rue Passe 20',
            'ville' => 'Bruxelles',
            'code_postal' => '1000',
        ]);

        Feedback::factory()->create([
            'rendez_vous_id' => $past->id,
            'booking_id' => $past->id,
            'client_id' => $client->id,
            'employe_id' => $past->employe_id,
        ]);

        $component = Livewire::actingAs($client)->test(ClientDashboard::class)->assertOk();

        $instance = $component->instance();

        $this->assertTrue($instance->rendezVousAvenir->contains('id', $future->id));
        $this->assertTrue($instance->rendezVousPasse->contains('id', $past->id));

        $stats = $instance->statsClient;
        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['avenir']);
        $this->assertSame(1, $stats['termine']);
        $this->assertSame(1, $stats['feedbacks']);

        $this->assertNotNull($instance->prochainRendezVous);
        $this->assertSame($future->id, $instance->prochainRendezVous->id);
        $this->assertNotNull($instance->dernierRendezVous);

        $this->assertGreaterThanOrEqual(1, $instance->adressesRecentes->count());

        // Standard client: no zone, no organisation -> empty coverage / services collections.
        $this->assertTrue($instance->coverageZones->isEmpty());
        $this->assertTrue($instance->availableServices->isEmpty());
        $this->assertTrue($instance->organizationSitesSummary->isEmpty());
        $this->assertTrue($instance->favoriteEmployes->isEmpty());

        $this->assertFalse($instance->isPremiumClient());
        $this->assertSame('Standard', $instance->accountContext['type_label']);
    }

    public function test_tri_descending_orders_upcoming_bookings(): void
    {
        $client = User::factory()->client()->create();

        $soon = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => BookingStatus::CONFIRME,
            'date' => now()->addDays(1)->toDateString(),
            'heure' => '09:00:00',
        ]);

        $later = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => BookingStatus::CONFIRME,
            'date' => now()->addDays(10)->toDateString(),
            'heure' => '09:00:00',
        ]);

        $component = Livewire::actingAs($client)
            ->test(ClientDashboard::class)
            ->set('tri', 'desc')
            ->assertOk();

        $ids = $component->instance()->rendezVousAvenir->pluck('id')->all();

        $this->assertSame([$later->id, $soon->id], array_slice($ids, 0, 2));
    }

    public function test_modifier_opens_edition_then_fermer_edition_resets_state(): void
    {
        $client = User::factory()->client()->create();

        $rdv = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => BookingStatus::CONFIRME,
            'date' => now()->addDays(4)->toDateString(),
            'heure' => '10:30:00',
        ]);

        Livewire::actingAs($client)
            ->test(ClientDashboard::class)
            ->call('modifier', $rdv->id)
            ->assertSet('editRdvId', $rdv->id)
            ->assertSet('editDate', $rdv->date->format('Y-m-d'))
            ->assertSet('editHeure', '10:30')
            ->call('fermerEdition')
            ->assertSet('editRdvId', null)
            ->assertSet('editDate', null)
            ->assertSet('editHeure', null)
            ->assertHasNoErrors();
    }

    public function test_enregistrer_modif_persists_changes_and_toasts(): void
    {
        $client = User::factory()->client()->create();

        $rdv = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => BookingStatus::CONFIRME,
            'date' => now()->addDays(4)->toDateString(),
            'heure' => '10:00:00',
        ]);

        $newDate = now()->addDays(6)->toDateString();

        Livewire::actingAs($client)
            ->test(ClientDashboard::class)
            ->call('modifier', $rdv->id)
            ->set('editDate', $newDate)
            ->set('editHeure', '15:30')
            ->call('enregistrerModif')
            ->assertDispatched('toast')
            ->assertSet('editRdvId', null)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bookings', [
            'id' => $rdv->id,
            'status' => BookingStatus::EN_ATTENTE,
        ]);

        $this->assertSame($newDate, $rdv->fresh()->date->format('Y-m-d'));
    }

    public function test_premium_client_with_organisation_reports_premium_context_and_finance_snapshot(): void
    {
        $organization = OrganizationAccount::factory()->create([
            'is_multisite' => true,
        ]);

        $client = User::factory()->premiumClient()->create([
            'organization_account_id' => $organization->id,
        ]);

        OrganizationSite::factory()->count(2)->create([
            'organization_account_id' => $organization->id,
            'is_active' => true,
        ]);

        FinanceQuote::factory()->count(2)->create([
            'client_id' => $client->id,
            'organization_account_id' => $organization->id,
        ]);

        FinanceInvoice::factory()->create([
            'client_id' => $client->id,
            'organization_account_id' => $organization->id,
            'status' => 'overdue',
            'balance_due' => 150.00,
        ]);

        FinanceInvoice::factory()->create([
            'client_id' => $client->id,
            'organization_account_id' => $organization->id,
            'status' => 'issued',
            'balance_due' => 50.00,
        ]);

        $instance = Livewire::actingAs($client)
            ->test(ClientDashboard::class)
            ->assertOk()
            ->instance();

        $this->assertTrue($instance->isPremiumClient());

        $context = $instance->accountContext;
        // organisation linked to a CLIENT_COMPANY account => the entreprise branch wins.
        $this->assertSame('Entreprise', $context['type_label']);
        $this->assertSame($organization->name, $context['organization_name']);
        $this->assertTrue($context['has_multisite']);

        $this->assertGreaterThanOrEqual(2, $instance->organizationSitesSummary->count());

        $snapshot = $instance->financeSnapshot;
        $this->assertSame(2, $snapshot['quotes_count']);
        $this->assertSame(2, $snapshot['invoices_count']);
        $this->assertSame(200.0, $snapshot['outstanding_total']);
        $this->assertSame(1, $snapshot['overdue_count']);
    }
}
