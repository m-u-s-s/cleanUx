<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Livewire\Client\PrendreRendezVous;
use App\Models\Booking;
use App\Models\CustomerProfile;
use App\Models\Disponibilite;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Booking\EmployeeAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * SP3 Task 8 (DoD #4) — câblage de PreferredCompanyResolver dans l'UI web.
 *
 * Quand un client premium impose une SOCIÉTÉ qui n'a aucun worker disponible sur
 * le créneau demandé, le formulaire (PrendreRendezVous) doit, après création,
 * exposer ses prochaines disponibilités (preferredCompanyMessage +
 * preferredCompanyAlternativeSlots) — miroir exact du flux presta préféré SP2.
 */
class PreferredCompanyAvailabilityTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /**
     * Crée un worker société PAR AILLEURS éligible (User actif rattaché à la zone,
     * ProviderProfile company_worker actif+vérifié rattaché à l'org, disponibilité
     * couvrant 08:00-18:00 sur la date) et le tagge du métier du service catalog —
     * requis car EligibleCompaniesResolver applique un filtre métier STRICT lors de
     * la validation d'éligibilité de la société choisie (palier premium).
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

    private function premiumClient(): User
    {
        $client = User::factory()->client()->create();
        CustomerProfile::query()->create([
            'user_id' => $client->id,
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);

        return $client->fresh();
    }

    /**
     * Monte le formulaire pré-rempli pour le client premium, sur la zone couverte,
     * avec la société imposée, prêt à `validerRdv` sur le créneau (date, 10:00).
     *
     * @return array{component:Testable, date:string}
     */
    private function bookingComponentFor(User $client, array $context, int $orgId, string $date): array
    {
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
            ->set('rdvDate', $date)
            ->set('rdvHeure', '10:00')
            ->set('assignedProviderOrganizationId', $orgId);

        return ['component' => $component, 'date' => $date];
    }

    #[Test]
    public function company_without_available_worker_surfaces_alternative_slots_after_creation(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        // Un prestataire indépendant générique dispo sur le créneau pour que le slot
        // PASSE la validation (le formulaire exige ≥1 employé dispo dans la zone) —
        // il n'appartient PAS à la société choisie et ne change rien à son indispo.
        $filler = User::factory()->employe()->create();
        $this->assignEmployeeToZone($filler, $context['zone'], [], ['date' => $date]);

        $org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
        ]);

        // Seul worker de l'org : dispo en théorie 08:00-18:00 le jour demandé...
        $worker = $this->companyWorker($zoneId, $org->id, (int) $context['service']->trade_id, $date);

        // ...mais OCCUPÉ par un RDV concurrent confirmé chevauchant le créneau 10:00,
        // si bien que la société n'a AUCUN worker dispo sur le créneau demandé.
        $otherClient = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        Booking::factory()->create([
            'client_id' => $otherClient->id,
            'employe_id' => $worker->id,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'duree_estimee' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'confirme',
        ]);

        $client = $this->premiumClient();

        $component = $this->bookingComponentFor($client, $context, $org->id, $date)['component']
            ->call('validerRdv')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        // La société est bien persistée sur le booking créé.
        $this->assertDatabaseHas('bookings', [
            'client_id' => $client->id,
            'assigned_provider_organization_id' => $org->id,
        ]);

        // DoD #4 : message société + créneaux alternatifs exposés à l'UI.
        $this->assertNotNull($component->get('preferredCompanyMessage'));

        $slots = $component->get('preferredCompanyAlternativeSlots');
        $this->assertNotEmpty($slots);

        // Les créneaux proposés correspondent à des disponibilités RÉELLES du worker
        // de la société (le resolver ne propose qu'un créneau effectivement libre),
        // et ne ré-offrent jamais le créneau saturé d'origine (jour + 10:00).
        $availability = app(EmployeeAvailabilityService::class);
        $zoneModel = $context['zone'];
        foreach ($slots as $slot) {
            $this->assertFalse(
                $slot['date'] === $date && $slot['heure'] === '10:00',
                'le créneau saturé d’origine ne doit jamais être reproposé',
            );
            $this->assertTrue(
                $availability->employeeIsAvailableForSlot($worker->id, $slot['date'], $slot['heure'], $zoneModel, 90),
                "créneau proposé non réellement dispo pour le worker: {$slot['date']} {$slot['heure']}",
            );
        }
    }

    #[Test]
    public function booking_without_a_chosen_company_surfaces_no_company_noise(): void
    {
        // Cas inverse : aucune société imposée → le flux société ne doit produire
        // ni message ni créneaux (le refresh early-return sur l'absence d'org).
        $context = $this->createCoverageContext();

        $employee = User::factory()->employe()->create();
        $date = now()->addDays(3)->toDateString();
        $this->assignEmployeeToZone($employee, $context['zone'], [], ['date' => $date]);

        $client = User::factory()->client()->create();
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
            ->set('rdvDate', $date)
            ->set('rdvHeure', '10:00')
            ->call('validerRdv')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $this->assertNull($component->get('preferredCompanyMessage'));
        $this->assertSame([], $component->get('preferredCompanyAlternativeSlots'));
    }
}
