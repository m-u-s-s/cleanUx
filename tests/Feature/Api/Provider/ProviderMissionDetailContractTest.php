<?php

namespace Tests\Feature\Api\Provider;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verrouille le contrat PLAT de GET /api/provider/missions/{id} et /active.
 *
 * Miroir de ProviderAssignmentInboxContractTest : l'inbox avait été aplatie pour coller au type
 * TS mobile, mais la DESTINATION de la carte (l'écran de détail) était restée imbriquée
 * ({ booking: {...}, client: {...} }) alors que le type Mission de mobile/provider est plat.
 * Résultat : titre vide, « undefined, undefined » en adresse, et TrackingScreen incapable de
 * calculer distance, ETA et géofence d'arrivée faute de latitude/longitude.
 *
 * Le second défaut couvert ici : l'eager load portait sur la relation booking(), qui choisit sa
 * FK depuis $this->booking_id — impossible en eager load, où Laravel résout la relation sur une
 * instance vierge et retombe donc toujours sur rendez_vous_id.
 */
class ProviderMissionDetailContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_a_flat_payload_for_a_mission_created_with_booking_id(): void
    {
        $provider = $this->makeProvider();
        $client = User::factory()->create(['name' => 'Paul Klee', 'phone' => '+32471000123']);
        $booking = $this->makeBooking($client);
        $mission = $this->makeMission(['booking_id' => $booking->id], $provider);

        $response = $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}")
            ->assertOk();

        $response->assertJsonPath('data.id', $mission->id);
        $response->assertJsonPath('data.status', 'assigned');
        $response->assertJsonPath('data.service_name', 'Nettoyage domicile');
        $response->assertJsonPath('data.client_name', 'Paul Klee');
        $response->assertJsonPath('data.client_phone', '+32471000123');
        $response->assertJsonPath('data.address', '12 rue du Test');
        $response->assertJsonPath('data.city', 'Bruxelles');
        $response->assertJsonPath('data.postal_code', '1000');
        // Prix non rond volontairement : json_encode((float) 120) rend "120", donc un montant
        // rond ne prouverait rien sur le type renvoyé.
        $response->assertJsonPath('data.total_price', 120.5);
        $response->assertJsonPath('data.notes', 'Apporter le matériel');

        // Aucune enveloppe imbriquée ne doit subsister : c'est elle qui rendait tous les champs
        // ci-dessus indéfinis côté mobile.
        $payload = $response->json('data');
        $this->assertArrayNotHasKey('booking', $payload);
        $this->assertArrayNotHasKey('client', $payload);
    }

    /**
     * Deux chemins de création écrivent chacun une colonne différente vers bookings.id :
     * CreateBookingFromApiAction / ProcessRecurringBookings écrivent booking_id, tandis que
     * MissionFromRendezVousSyncService / MissionPaymentService (et le factory par défaut)
     * écrivent rendez_vous_id. Le détail doit résoudre les deux, pas seulement le second.
     */
    public function test_show_resolves_bookings_created_via_the_legacy_rendez_vous_path(): void
    {
        $provider = $this->makeProvider();
        $client = User::factory()->create(['name' => 'Rene Magritte']);
        $booking = $this->makeBooking($client);
        $mission = $this->makeMission(['rendez_vous_id' => $booking->id], $provider);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}")
            ->assertOk()
            ->assertJsonPath('data.client_name', 'Rene Magritte')
            ->assertJsonPath('data.service_name', 'Nettoyage domicile')
            ->assertJsonPath('data.address', '12 rue du Test')
            ->assertJsonPath('data.city', 'Bruxelles');
    }

    /**
     * TrackingScreen sort tôt sur `if (!mission?.latitude || !mission?.longitude) return;` :
     * sans ces deux clés, ni distance, ni ETA, ni géofence d'arrivée à 150 m.
     */
    public function test_show_exposes_the_destination_coordinates_the_tracking_screen_needs(): void
    {
        $provider = $this->makeProvider();
        $booking = $this->makeBooking(User::factory()->create());
        $mission = $this->makeMission([
            'booking_id' => $booking->id,
            'destination_lat' => 50.8503,
            'destination_lng' => 4.3517,
        ], $provider);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}")
            ->assertOk()
            ->assertJsonPath('data.latitude', 50.8503)
            ->assertJsonPath('data.longitude', 4.3517);
    }

    public function test_show_falls_back_to_the_booking_destination_when_the_mission_is_not_geocoded(): void
    {
        $provider = $this->makeProvider();
        $booking = $this->makeBooking(User::factory()->create());
        $booking->update(['destination_lat' => 51.0543, 'destination_lng' => 3.7174]);
        $mission = $this->makeMission(['booking_id' => $booking->id], $provider);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}")
            ->assertOk()
            ->assertJsonPath('data.latitude', 51.0543)
            ->assertJsonPath('data.longitude', 3.7174);
    }

    public function test_show_returns_null_coordinates_when_nothing_is_geocoded(): void
    {
        $provider = $this->makeProvider();
        $booking = $this->makeBooking(User::factory()->create());
        $mission = $this->makeMission(['booking_id' => $booking->id], $provider);

        $payload = $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}")
            ->assertOk()
            ->json('data');

        // assertJsonPath(..., null) passe aussi quand la clé est absente : on vérifie la présence
        // de la clé en plus de sa valeur.
        $this->assertArrayHasKey('latitude', $payload);
        $this->assertArrayHasKey('longitude', $payload);
        $this->assertNull($payload['latitude']);
        $this->assertNull($payload['longitude']);
    }

    /**
     * Les écrans affichent brut « {scheduled_date} à {scheduled_time} » : un Carbon non formaté
     * y sortirait en ISO-8601 complet ("2026-06-15T00:00:00.000000Z").
     */
    public function test_show_normalises_the_schedule_to_a_date_and_a_time(): void
    {
        $provider = $this->makeProvider();
        $booking = $this->makeBooking(User::factory()->create());
        $mission = $this->makeMission(['booking_id' => $booking->id], $provider);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}")
            ->assertOk()
            ->assertJsonPath('data.scheduled_date', now()->addDay()->toDateString())
            ->assertJsonPath('data.scheduled_time', '10:00');
    }

    public function test_active_returns_the_same_flat_shape(): void
    {
        $provider = $this->makeProvider();
        $client = User::factory()->create(['name' => 'Paul Klee']);
        $booking = $this->makeBooking($client);
        $this->makeMission(['booking_id' => $booking->id], $provider);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/missions/active')
            ->assertOk()
            ->assertJsonPath('data.0.service_name', 'Nettoyage domicile')
            ->assertJsonPath('data.0.client_name', 'Paul Klee')
            ->assertJsonPath('data.0.address', '12 rue du Test')
            ->assertJsonPath('data.0.city', 'Bruxelles')
            ->assertJsonPath('data.0.scheduled_date', now()->addDay()->toDateString())
            ->assertJsonPath('data.0.scheduled_time', '10:00');
    }

    public function test_active_resolves_bookings_created_via_the_legacy_rendez_vous_path(): void
    {
        $provider = $this->makeProvider();
        $client = User::factory()->create(['name' => 'Rene Magritte']);
        $booking = $this->makeBooking($client);
        $this->makeMission(['rendez_vous_id' => $booking->id], $provider);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/missions/active')
            ->assertOk()
            ->assertJsonPath('data.0.client_name', 'Rene Magritte')
            ->assertJsonPath('data.0.address', '12 rue du Test');
    }

    protected function makeProvider(): User
    {
        $user = User::factory()->employe()->create();

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'individual',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $user->fresh();
    }

    protected function makeBooking(User $client): Booking
    {
        $service = ServiceCatalog::factory()->create(['name' => 'Nettoyage domicile']);

        return Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'service_catalog_id' => $service->id,
            'address' => '12 rue du Test',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'customer_comment' => 'Apporter le matériel',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ]);
    }

    protected function makeMission(array $attributes, User $provider): Mission
    {
        $mission = Mission::create(array_merge([
            'status' => 'assigned',
            'planned_start_at' => now()->addDay(),
            'client_price' => 120.5,
        ], $attributes));

        MissionAssignment::factory()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'accepted',
            'assigned_at' => now(),
        ]);

        return $mission;
    }
}
