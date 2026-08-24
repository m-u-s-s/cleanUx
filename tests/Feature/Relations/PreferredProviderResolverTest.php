<?php

namespace Tests\Feature\Relations;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Disponibilite;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Booking\PreferredProviderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/** SP2 Task 4 — PreferredProviderResolver. */
class PreferredProviderResolverTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /** Crée un prestataire PAR AILLEURS éligible : User actif rattaché à la zone, ProviderProfile actif+vérifié. */
    private function eligibleProvider(int $zoneId): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $zoneId,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $user;
    }

    private function makeBooking(int $zoneId, string $date, int $preferredId): Booking
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        return Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => null,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'estimated_duration_minutes' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'en_attente',
            'preferred_provider_user_id' => $preferredId,
        ]);
    }

    public function test_preferred_provider_available_is_assigned(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        $x = $this->eligibleProvider($zoneId);

        // X dispo sur le créneau demandé.
        Disponibilite::create([
            'user_id' => $x->id,
            'date' => $date,
            'heure_debut' => '08:00:00',
            'heure_fin' => '18:00:00',
        ]);

        $booking = $this->makeBooking($zoneId, $date, $x->id);

        $result = app(PreferredProviderResolver::class)->resolve($booking->fresh());

        $this->assertSame('assigned', $result['status']);
        $this->assertSame($x->id, $result['provider']->id);
        $this->assertSame([], $result['alternative_slots']);
    }

    public function test_preferred_provider_unavailable_returns_alternative_slots(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        $x = $this->eligibleProvider($zoneId);

        // X est dispo en théorie (08:00-18:00) sur le jour demandé...
        Disponibilite::create([
            'user_id' => $x->id,
            'date' => $date,
            'heure_debut' => '08:00:00',
            'heure_fin' => '18:00:00',
        ]);

        // ...mais OCCUPÉ par un RDV concurrent confirmé chevauchant le créneau 10:00.
        $otherClient = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        Booking::factory()->create([
            'client_id' => $otherClient->id,
            'employe_id' => $x->id,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'estimated_duration_minutes' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'confirme',
        ]);

        $booking = $this->makeBooking($zoneId, $date, $x->id);

        $result = app(PreferredProviderResolver::class)->resolve($booking->fresh());

        $this->assertSame('unavailable', $result['status']);
        $this->assertSame($x->id, $result['provider']->id);
        $this->assertIsArray($result['alternative_slots']);
    }
}
