<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\PrendreRendezVous;
use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * Targets the still-uncovered guards, error paths and secondary actions of the
 * HandlesBookingCreation trait, driven through its real host component
 * (PrendreRendezVous): the unauthenticated redirect guard, the
 * envoyerDemande delegation, the "no employee available" failure, the foreign
 * organization-site rejection, and the corporate-context / aggregated-comment
 * assembly on a successful submission.
 */
class HandlesBookingCreationCoverageBatch18Test extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /**
     * Mounts a fully bookable, ready-to-validate booking form for the given
     * client (covered zone + one assigned, available employee).
     *
     * @return array{component:Testable, context:array, date:string}
     */
    private function readyBookingFor(User $client, array $set = []): array
    {
        $context = $this->createCoverageContext();
        $employee = User::factory()->employe()->create();
        $bookingDate = now()->addDays(2)->toDateString();
        $this->assignEmployeeToZone($employee, $context['zone'], [], ['date' => $bookingDate]);

        $this->actingAs($client);

        $component = Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', $context['service']->code ?: $context['service']->slug)
            ->set('type_lieu', 'appartement')
            ->set('frequence', 'ponctuel')
            ->set('surface', 'moins_50')
            ->set('adresse', 'Rue de la Loi 1')
            ->set('ville', $context['postalCode']->city_name)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('telephone_client', '0470000000')
            ->set('priorite', 'normale')
            ->set('rdvDate', $bookingDate)
            ->set('rdvHeure', '10:00');

        foreach ($set as $property => $value) {
            $component->set($property, $value);
        }

        return ['component' => $component, 'context' => $context, 'date' => $bookingDate];
    }

    #[Test]
    public function guest_submission_is_redirected_to_authentication(): void
    {
        Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', 'nettoyage_standard')
            ->call('validerRdv')
            ->assertRedirect(route('register'));

        $this->assertSame(
            route('booking.create', ['resume' => 1]),
            session('url.intended'),
        );
    }

    #[Test]
    public function envoyer_demande_delegates_to_the_validation_workflow(): void
    {
        $client = User::factory()->client()->create();

        $this->readyBookingFor($client)['component']
            ->call('envoyerDemande')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $this->assertDatabaseHas('bookings', [
            'client_id' => $client->id,
            'status' => 'en_attente',
        ]);
    }

    #[Test]
    public function booking_fails_when_no_employee_is_available_for_the_slot(): void
    {
        $client = User::factory()->client()->create();
        $context = $this->createCoverageContext();
        $bookingDate = now()->addDays(2)->toDateString();

        $this->actingAs($client);

        // Coverage is bookable but no employee is assigned to the zone, so the
        // slot resolver returns null and the form posts an error on rdvHeure.
        Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', $context['service']->code ?: $context['service']->slug)
            ->set('type_lieu', 'appartement')
            ->set('frequence', 'ponctuel')
            ->set('surface', 'moins_50')
            ->set('adresse', 'Rue de la Loi 1')
            ->set('ville', $context['postalCode']->city_name)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('telephone_client', '0470000000')
            ->set('priorite', 'normale')
            ->set('rdvDate', $bookingDate)
            ->set('rdvHeure', '10:00')
            ->call('validerRdv')
            ->assertHasErrors('rdvHeure')
            ->assertNotSet('step', 5);

        $this->assertDatabaseMissing('bookings', ['client_id' => $client->id]);
    }

    #[Test]
    public function booking_is_refused_when_the_selected_site_is_not_in_the_clients_organization(): void
    {
        $client = User::factory()->client()->create();

        $foreignOrg = OrganizationAccount::factory()->create();
        $foreignSite = OrganizationSite::factory()->create([
            'organization_account_id' => $foreignOrg->id,
            'is_active' => true,
        ]);

        $this->actingAs($client);

        Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', 'nettoyage_standard')
            ->set('organization_site_id', $foreignSite->id)
            ->call('validerRdv')
            ->assertHasErrors('organization_site_id')
            ->assertHasErrors('postal_code_input')
            ->assertNotSet('step', 5);

        $this->assertDatabaseMissing('bookings', ['client_id' => $client->id]);
    }

    #[Test]
    public function successful_booking_aggregates_extra_fields_into_the_client_comment(): void
    {
        $client = User::factory()->client()->create();

        $this->readyBookingFor($client, [
            'commentaire_client' => 'Merci de sonner deux fois.',
            'site_instructions' => 'Badge requis au RDC',
            'purchase_order_reference' => 'PO-9001',
            'cost_center' => 'CC-42',
            'materiel_specifique' => 'Aspirateur pro',
        ])['component']
            ->call('validerRdv')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $booking = Booking::query()
            ->where('client_id', $client->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($booking);
        $this->assertStringContainsString('Merci de sonner deux fois.', (string) $booking->commentaire_client);
        $this->assertStringContainsString('Consignes site : Badge requis au RDC', (string) $booking->commentaire_client);
        $this->assertStringContainsString('Référence PO : PO-9001', (string) $booking->commentaire_client);
        $this->assertStringContainsString('Centre de coût : CC-42', (string) $booking->commentaire_client);
        $this->assertSame(['Aspirateur pro'], $booking->materiel_specifique);
    }
}
