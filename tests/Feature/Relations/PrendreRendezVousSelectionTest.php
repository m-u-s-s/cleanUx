<?php

namespace Tests\Feature\Relations;

use App\Livewire\Client\PrendreRendezVous;
use App\Models\BookingFavorite;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * Task 6 SP2 — l'UI web du formulaire de réservation expose les 3 paliers de
 * sélection du type de prestataire : TYPE (indépendant/société/peu importe),
 * re-réservation d'un FAVORI, et (si PREMIUM) choix d'un nouveau prestataire.
 */
class PrendreRendezVousSelectionTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /**
     * Monte le contexte minimal de réservation (zone couverte + employé
     * affecté disponible) et renvoie le composant Livewire pré-rempli, prêt
     * à `validerRdv`. Les surcharges permettent d'injecter les propriétés de
     * sélection prestataire propres à chaque cas.
     *
     * @return array{component:Testable, context:array, date:string}
     */
    private function bookingComponentFor(User $client, array $set = []): array
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

    private function premiumProfileClient(): User
    {
        // Plan User "standard" pour ne PAS déclencher la règle employe_id
        // requise (canChooseEmployee), mais CustomerProfile premium pour que
        // ProviderSelectionResolver autorise le choix d'un nouveau presta.
        $client = User::factory()->client()->create();
        CustomerProfile::query()->create([
            'user_id' => $client->id,
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);

        return $client->fresh();
    }

    #[Test]
    public function type_preference_is_persisted_on_the_created_booking(): void
    {
        $client = User::factory()->client()->create();

        $this->bookingComponentFor($client, ['providerTypePreference' => 'independent'])['component']
            ->call('validerRdv')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $this->assertDatabaseHas('bookings', [
            'client_id' => $client->id,
            'provider_type_preference' => 'independent',
            'preferred_provider_user_id' => null,
        ]);
    }

    #[Test]
    public function rebooking_a_favorite_provider_is_allowed_for_non_premium_client(): void
    {
        $client = User::factory()->client()->create();
        $favoriteProvider = User::factory()->employe()->create();

        BookingFavorite::query()->create([
            'client_user_id' => $client->id,
            'preferred_provider_user_id' => $favoriteProvider->id,
        ]);

        $this->bookingComponentFor($client, ['preferredProviderUserId' => $favoriteProvider->id])['component']
            ->call('validerRdv')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $this->assertDatabaseHas('bookings', [
            'client_id' => $client->id,
            'preferred_provider_user_id' => $favoriteProvider->id,
        ]);
    }

    #[Test]
    public function non_premium_client_choosing_a_non_favorite_provider_is_refused(): void
    {
        $client = User::factory()->client()->create();
        $stranger = User::factory()->employe()->create();

        $this->bookingComponentFor($client, ['preferredProviderUserId' => $stranger->id])['component']
            ->call('validerRdv')
            ->assertHasErrors('preferredProviderUserId')
            ->assertNotSet('step', 5);

        $this->assertDatabaseMissing('bookings', [
            'client_id' => $client->id,
        ]);
    }

    #[Test]
    public function step_four_renders_the_provider_type_selector_and_favorites(): void
    {
        $client = User::factory()->client()->create();
        $favoriteProvider = User::factory()->employe()->create(['name' => 'Fatima Cleaning']);

        BookingFavorite::query()->create([
            'client_user_id' => $client->id,
            'preferred_provider_user_id' => $favoriteProvider->id,
            'label' => 'Fatima Cleaning',
        ]);

        $this->bookingComponentFor($client)['component']
            ->set('step', 4)
            ->assertOk()
            ->assertSee('Type de prestataire')
            ->assertSee('Peu importe')
            ->assertSee('Mes prestataires favoris')
            ->assertSee('Fatima Cleaning');
    }

    #[Test]
    public function premium_client_can_choose_a_new_provider(): void
    {
        $client = $this->premiumProfileClient();
        $stranger = User::factory()->employe()->create();

        $this->bookingComponentFor($client, ['preferredProviderUserId' => $stranger->id])['component']
            ->call('validerRdv')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $this->assertDatabaseHas('bookings', [
            'client_id' => $client->id,
            'preferred_provider_user_id' => $stranger->id,
        ]);
    }
}
