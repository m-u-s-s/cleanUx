<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Livewire\Client\PrendreRendezVous;
use App\Models\Booking;
use App\Models\Disponibilite;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * SP4 Task 6 — preuve e2e bout-en-bout : un membre d'une CLIENT_COMPANY sous
 * contrat-cadre ACTIF crée une réservation via le vrai formulaire web
 * (PrendreRendezVous). Le hook contrat (HandlesBookingCreation → CreateBookingAction)
 * route automatiquement vers la société partenaire du contrat et applique le tarif
 * négocié sur devis_estime, sans sélection explicite. Calque le pattern e2e SP3
 * (BrowseCompaniesSelectionTest).
 */
class ContractAwareBookingTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /**
     * Worker société éligible (calque companyWorker de BrowseCompaniesSelectionTest) :
     * rend la société partenaire réservable pour zone + métier + créneau.
     */
    private function companyWorker(int $zoneId, int $orgId, int $tradeId, string $date): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $zoneId,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $orgId,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        Disponibilite::create([
            'user_id' => $user->id,
            'date' => $date,
            'heure_debut' => '08:00:00',
            'heure_fin' => '18:00:00',
        ]);

        $user->trades()->sync([$tradeId]);

        return $user;
    }

    /**
     * Client membre d'une CLIENT_COMPANY (current_organization_id posé) sous un
     * contrat-cadre actif avec une société partenaire éligible.
     *
     * @return array{component:Testable, client:User, clientOrg:OrganizationAccount, provider:OrganizationAccount, contract:OrganizationContract, service:int, date:string}
     */
    private function contractedBookingContext(array $contractOverrides = [], array $set = []): array
    {
        $trade = Trade::factory()->create();

        $context = $this->createCoverageContext([
            'service' => ['trade_id' => $trade->id],
        ]);
        $zone = $context['zone'];
        $service = $context['service'];
        $bookingDate = now()->addDays(2)->toDateString();

        // Employé indépendant disponible pour l'affectation de créneau (chemin
        // non-récurrent) ; la société partenaire du contrat se persiste par-dessus.
        $employee = User::factory()->employe()->create();
        $this->assignEmployeeToZone($employee, $zone, [], ['date' => $bookingDate]);

        $clientOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::CLIENT_COMPANY->value,
            'name' => 'Acme Corp',
        ]);

        $provider = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'rating_avg' => 4.7,
            'rating_count' => 9,
            'name' => 'Alpha Clean',
        ]);
        $this->companyWorker($zone->id, $provider->id, $trade->id, $bookingDate);

        $contract = OrganizationContract::factory()->create(array_merge([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => $provider->id,
            'status' => 'active',
            'negotiated_discount_percent' => 20,
            'allowed_service_catalog_ids' => [$service->id],
        ], $contractOverrides));

        $client = User::factory()->client()->create([
            'current_organization_id' => $clientOrg->id,
            'organization_account_id' => $clientOrg->id,
        ]);
        OrganizationMember::create([
            'organization_account_id' => $clientOrg->id,
            'user_id' => $client->id,
            'role' => 'requester',
            'status' => 'active',
        ]);

        // Le client est membre d'une CLIENT_COMPANY (isEntrepriseCustomer) : le
        // formulaire exige un site d'organisation. On en crée un, rattaché à la zone
        // couverte, et on le sélectionne (le hook contrat opère par-dessus).
        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'service_zone_id' => $zone->id,
            'postal_code_id' => $context['postalCode']->id,
            'postal_code' => $context['postalCode']->code,
            'city' => $context['postalCode']->city_name,
            'address' => 'Rue de la Loi 1',
            'address_line_1' => 'Rue de la Loi 1',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $this->actingAs($client);

        $component = Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', $service->code ?: $service->slug)
            ->set('organization_site_id', $site->id)
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
            ->set('devis_estime', 100.0);

        foreach ($set as $property => $value) {
            $component->set($property, $value);
        }

        return [
            'component' => $component,
            'client' => $client,
            'clientOrg' => $clientOrg,
            'provider' => $provider,
            'contract' => $contract,
            'service' => $service->id,
            'date' => $bookingDate,
        ];
    }

    #[Test]
    public function contracted_member_booking_is_routed_to_partner_org_with_negotiated_price(): void
    {
        $ctx = $this->contractedBookingContext();

        $ctx['component']
            ->call('validerRdv')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        // Routage contractuel : société partenaire + lien contrat persistés.
        $this->assertDatabaseHas('bookings', [
            'client_id' => $ctx['client']->id,
            'organization_contract_id' => $ctx['contract']->id,
            'assigned_provider_organization_id' => $ctx['provider']->id,
        ]);

        // Tarif négocié (100 - 20% = 80) reflété dans devis_estime.
        $booking = Booking::query()
            ->where('client_id', $ctx['client']->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($booking);
        $this->assertSame(80.0, round((float) $booking->devis_estime, 2));
    }

    #[Test]
    public function contracted_member_booking_is_refused_when_required_po_is_missing(): void
    {
        $ctx = $this->contractedBookingContext([
            'requires_purchase_order' => true,
        ]);

        // Aucun purchase_order_reference fourni → violation dure → erreur Livewire,
        // pas d'étape 5, aucune réservation créée.
        $ctx['component']
            ->call('validerRdv')
            ->assertHasErrors('purchase_order_reference')
            ->assertNotSet('step', 5);

        $this->assertDatabaseMissing('bookings', [
            'client_id' => $ctx['client']->id,
        ]);
    }

    #[Test]
    public function contracted_member_booking_passes_when_required_po_is_provided(): void
    {
        $ctx = $this->contractedBookingContext(
            ['requires_purchase_order' => true],
            ['purchase_order_reference' => 'PO-12345'],
        );

        $ctx['component']
            ->call('validerRdv')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $this->assertDatabaseHas('bookings', [
            'client_id' => $ctx['client']->id,
            'organization_contract_id' => $ctx['contract']->id,
            'assigned_provider_organization_id' => $ctx['provider']->id,
        ]);
    }
}
