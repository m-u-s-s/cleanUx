<?php

namespace Tests\Feature\Relations;

use App\Models\User;
use App\Services\Booking\CreateBookingAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class CreateBookingPersistsPreferenceTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    public function test_create_booking_action_persists_provider_type_preference_and_preferred_provider(): void
    {
        $context = $this->createCoverageContext();

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $assignedEmployee = User::factory()->create([
            'role' => User::ROLE_EMPLOYE, 'is_active' => true,
            'primary_service_zone_id' => $context['zone']->id,
        ]);
        $preferred = User::factory()->create([
            'role' => User::ROLE_EMPLOYE, 'is_active' => true,
        ]);

        $action = app(CreateBookingAction::class);

        $rdv = $action->execute(
            client: $client,
            postal: $context['postalCode'],
            zone: $context['zone'],
            catalog: $context['service'],
            rule: $context['rule'],
            assignedEmployee: $assignedEmployee,
            data: [
                'date' => now()->addDay()->toDateString(),
                'heure' => '10:00',
                'service_zone_id' => $context['zone']->id,
                'postal_code_id' => $context['postalCode']->id,
                'service_identifier' => $context['service']->code ?: $context['service']->slug,
                'place_type' => 'maison',
                'frequence' => 'ponctuel',
                'surface' => '50_100',
                'adresse' => '1 rue Test',
                'ville' => $context['postalCode']->city_name,
                'code_postal' => $context['postalCode']->code,
                'telephone_client' => '+32490000000',
                'priorite' => 'normale',
                'options_prestation' => [],
                'zones_specifiques' => [],
                'materiel_specifique' => [],
                'is_recurrent' => false,
                'status' => 'en_attente',
                'booking_mode' => 'scheduled',
                'duree_estimee' => 90,
                'devis_estime' => 100.0,
                'provider_type_preference' => 'independent',
                'preferred_provider_user_id' => $preferred->id,
            ],
        );

        $this->assertNotNull($rdv);
        $this->assertSame('independent', $rdv->fresh()->provider_type_preference);
        $this->assertSame($preferred->id, $rdv->fresh()->preferred_provider_user_id);
    }
}
