<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Livewire\Client\BrowseCompanies;
use App\Livewire\Client\PrendreRendezVous;
use App\Models\CustomerProfile;
use App\Models\Disponibilite;
use App\Models\OrganizationAccount;
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
 * SP3 Task 8 — l'UI web du formulaire de réservation expose la sélection d'une
 * SOCIÉTÉ (palier premium). Miroir EXACT du pattern SP2 (BrowseProviders en mode
 * sélection émet un event capté par PrendreRendezVous), mais pour les sociétés.
 */
class BrowseCompaniesSelectionTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    private function providerCompany(): OrganizationAccount
    {
        return OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'rating_avg' => 4.7,
            'rating_count' => 9,
            'name' => 'Alpha Clean',
        ]);
    }

    private function premiumProfileClient(): User
    {
        $client = User::factory()->client()->create();
        CustomerProfile::query()->create([
            'user_id' => $client->id,
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);

        return $client->fresh();
    }

    #[Test]
    public function browse_companies_in_selection_mode_dispatches_the_company_selected_event(): void
    {
        $org = $this->providerCompany();

        Livewire::test(BrowseCompanies::class, ['selectionMode' => true])
            ->call('selectCompany', $org->id)
            ->assertDispatched('companySelected', organizationId: $org->id);
    }

    #[Test]
    public function browse_companies_without_selection_mode_does_not_dispatch_the_event(): void
    {
        $org = $this->providerCompany();

        Livewire::test(BrowseCompanies::class)
            ->call('selectCompany', $org->id)
            ->assertNotDispatched('companySelected');
    }

    #[Test]
    public function selecting_a_company_via_event_sets_the_org_clears_the_worker_and_closes_the_picker(): void
    {
        $client = $this->premiumProfileClient();
        $org = $this->providerCompany();

        $this->actingAs($client);

        Livewire::test(PrendreRendezVous::class)
            ->set('preferredProviderUserId', 999)
            ->set('showProviderPicker', true)
            ->call('onCompanySelected', $org->id)
            ->assertSet('assignedProviderOrganizationId', $org->id)
            ->assertSet('preferredProviderUserId', null)
            ->assertSet('showProviderPicker', false);
    }

    /**
     * Crée un worker société PAR AILLEURS éligible pour CE booking (User actif
     * rattaché à la zone, ProviderProfile company_worker actif+vérifié rattaché à
     * l'org, dispo couvrant le créneau, taggé du métier). Calque EXACT de
     * EligibleCompaniesResolverTest::companyWorker — rend la société réservable.
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
     * Monte le contexte e2e complet : zone couverte (service taggé d'un métier),
     * un employé indépendant disponible (pour l'affectation du créneau) ET une
     * société PROVIDER_COMPANY éligible (worker company du bon métier dans la zone).
     * Renvoie le composant PrendreRendezVous pré-rempli, prêt à `validerRdv`, avec
     * l'org éligible. Calque le `bookingComponentFor` de PrendreRendezVousSelectionTest.
     *
     * @return array{component:Testable, org:OrganizationAccount, date:string}
     */
    private function bookingComponentWithEligibleCompanyFor(User $client, array $set = []): array
    {
        $trade = Trade::factory()->create();

        $context = $this->createCoverageContext([
            'service' => ['trade_id' => $trade->id],
        ]);
        $zone = $context['zone'];
        $bookingDate = now()->addDays(2)->toDateString();

        // Employé indépendant disponible : permet à validerRdv d'affecter un
        // prestataire au créneau (chemin non-récurrent). La sélection SOCIÉTÉ
        // se persiste PAR-DESSUS via assigned_provider_organization_id.
        $employee = User::factory()->employe()->create();
        $this->assignEmployeeToZone($employee, $zone, [], ['date' => $bookingDate]);

        // Société prestataire ÉLIGIBLE pour zone + métier + créneau du booking.
        $org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'rating_avg' => 4.7,
            'rating_count' => 9,
            'name' => 'Alpha Clean',
        ]);
        $this->companyWorker($zone->id, $org->id, $trade->id, $bookingDate);

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

        return ['component' => $component, 'org' => $org, 'date' => $bookingDate];
    }

    /**
     * E2E — preuve de persistance bout-en-bout : un client PREMIUM pilote le
     * formulaire, choisit une SOCIÉTÉ éligible (via onCompanySelected, le listener
     * réel de l'event BrowseCompanies), soumet → assigned_provider_organization_id
     * arrive RÉELLEMENT en DB sur bookings, et le worker nominatif est null
     * (mutuellement exclusifs : l'org prime).
     */
    #[Test]
    public function premium_client_chosen_provider_company_is_persisted_to_the_created_booking(): void
    {
        $client = $this->premiumProfileClient();

        $built = $this->bookingComponentWithEligibleCompanyFor(
            $client,
            ['providerTypePreference' => 'company'],
        );

        $built['component']
            ->call('onCompanySelected', $built['org']->id)
            ->assertSet('assignedProviderOrganizationId', $built['org']->id)
            ->call('validerRdv')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $this->assertDatabaseHas('bookings', [
            'client_id' => $client->id,
            'assigned_provider_organization_id' => $built['org']->id,
            'preferred_provider_user_id' => null,
        ]);
    }

    /**
     * E2E (frontière sécurité) — un client NON premium qui choisit une société
     * (même éligible) est REFUSÉ : erreur posée sur assignedProviderOrganizationId,
     * aucune réservation créée. Miroir du gate premium côté backend (resolver).
     */
    #[Test]
    public function non_premium_client_choosing_a_company_is_refused_and_no_booking_is_created(): void
    {
        $client = User::factory()->client()->create();

        $built = $this->bookingComponentWithEligibleCompanyFor(
            $client,
            ['providerTypePreference' => 'company'],
        );

        $built['component']
            ->call('onCompanySelected', $built['org']->id)
            ->call('validerRdv')
            ->assertHasErrors('assignedProviderOrganizationId')
            ->assertNotSet('step', 5);

        $this->assertDatabaseMissing('bookings', [
            'client_id' => $client->id,
        ]);
    }
}
