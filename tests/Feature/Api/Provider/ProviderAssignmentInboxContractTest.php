<?php

namespace Tests\Feature\Api\Provider;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verrouille le contrat plat de GET /api/provider/assignments/inbox.
 *
 * Le sérialiseur renvoyait une structure imbriquée { mission: {...}, booking: {...} } alors que
 * le type TS mobile (MissionAssignment) et les deux écrans qui le consomment attendent un
 * payload plat — d'où des champs vides dans l'app et un missionId undefined à la navigation.
 */
class ProviderAssignmentInboxContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_returns_a_flat_payload_with_coordinates(): void
    {
        $provider = $this->makeProvider();
        $client = User::factory()->create(['name' => 'Paul Klee']);
        $booking = $this->makeBooking($client);
        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'planned_start_at' => now()->addDay(),
            'destination_lat' => 50.8503,
            'destination_lng' => 4.3517,
            'estimated_duration_minutes' => 90,
        ]);
        $this->makeAssignment($mission, $provider);

        $response = $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/assignments/inbox');

        $response->assertOk();
        $response->assertJsonPath('data.0.booking_id', $booking->id);
        $response->assertJsonPath('data.0.client_name', 'Paul Klee');
        $response->assertJsonPath('data.0.address', '12 rue du Test');
        $response->assertJsonPath('data.0.city', 'Bruxelles');
        $response->assertJsonPath('data.0.postal_code', '1000');
        $response->assertJsonPath('data.0.latitude', 50.8503);
        $response->assertJsonPath('data.0.longitude', 4.3517);
        $response->assertJsonPath('data.0.estimated_duration_minutes', 90);
        // Valeurs, pas seulement présence des clés : les casts `date` / `datetime:H:i` du modèle
        // ne s'appliquent qu'à la sérialisation DU MODÈLE, donc ces deux champs partaient en
        // ISO-8601 complet ("2026-06-15T00:00:00.000000Z") dans un écran qui les affiche brut.
        $response->assertJsonPath('data.0.scheduled_date', now()->addDay()->toDateString());
        $response->assertJsonPath('data.0.scheduled_time', '10:00');
        $response->assertJsonStructure([
            'data' => [
                ['id', 'mission_id', 'assignment_status', 'expires_at', 'remaining_seconds',
                    'booking_id', 'service_name', 'client_name', 'address', 'city', 'postal_code',
                    'scheduled_date', 'scheduled_time', 'latitude', 'longitude', 'estimated_duration_minutes'],
            ],
        ]);
    }

    public function test_inbox_returns_null_coordinates_when_the_mission_is_not_geocoded(): void
    {
        $provider = $this->makeProvider();
        $booking = $this->makeBooking(User::factory()->create());
        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'planned_start_at' => now()->addDay(),
        ]);
        $this->makeAssignment($mission, $provider);

        $response = $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/assignments/inbox')
            ->assertOk();

        // assertJsonPath('data.0.latitude', null) passe aussi quand la clé est absente : ça ne
        // prouve rien. On vérifie explicitement la présence de la clé en plus de sa valeur.
        $payload = $response->json('data.0');
        $this->assertArrayHasKey('latitude', $payload);
        $this->assertArrayHasKey('longitude', $payload);
        $this->assertNull($payload['latitude']);
        $this->assertNull($payload['longitude']);
    }

    /**
     * `missions.destination_lat` n'est pas toujours renseignée : seule la synchronisation depuis
     * une réservation géocode l'adresse, et les missions antérieures à ce correctif n'ont que la
     * destination portée par le dossier. L'inbox doit donc retomber dessus, exactement comme le
     * fait déjà l'écran de détail (ProviderMissionLifecycleController).
     */
    public function test_inbox_falls_back_to_the_booking_destination_when_the_mission_has_none(): void
    {
        $provider = $this->makeProvider();
        $booking = $this->makeBooking(User::factory()->create());
        $booking->update(['destination_lat' => 50.6402, 'destination_lng' => 5.5713]);
        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'planned_start_at' => now()->addDay(),
        ]);
        $this->makeAssignment($mission, $provider);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/assignments/inbox')
            ->assertOk()
            ->assertJsonPath('data.0.latitude', 50.6402)
            ->assertJsonPath('data.0.longitude', 5.5713);
    }

    /**
     * Anti-régression du bloquant : missions.start_lat porte la position GPS DU PRESTATAIRE,
     * écrite par MissionLifecycleService aux transitions `arrived` / `started`. L'inbox ne liste
     * que des lignes `assigned`, donc antérieures à ces transitions : s'en servir comme
     * destination affichait zéro marqueur en production, et aurait pointé le prestataire
     * lui-même plutôt que le client sur les missions déjà démarrées.
     */
    public function test_inbox_never_exposes_the_provider_telemetry_start_coordinates(): void
    {
        $provider = $this->makeProvider();
        $booking = $this->makeBooking(User::factory()->create());
        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'planned_start_at' => now()->addDay(),
            'start_lat' => 48.8566,
            'start_lng' => 2.3522,
        ]);
        $this->makeAssignment($mission, $provider);

        $payload = $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/assignments/inbox')
            ->assertOk()
            ->json('data.0');

        $this->assertNull($payload['latitude']);
        $this->assertNull($payload['longitude']);
    }

    /**
     * Deux chemins de création écrivent chacun une colonne différente vers bookings.id :
     * CreateBookingFromApiAction / ProcessRecurringBookings écrivent booking_id, tandis que
     * MissionFromRendezVousSyncService / MissionPaymentService (et le factory par défaut)
     * écrivent rendez_vous_id. L'inbox doit résoudre les deux, pas seulement le premier.
     */
    public function test_inbox_resolves_bookings_created_via_the_legacy_rendez_vous_path(): void
    {
        $provider = $this->makeProvider();
        $client = User::factory()->create(['name' => 'Rene Magritte']);
        $booking = $this->makeBooking($client);
        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'planned_start_at' => now()->addDay(),
        ]);
        $this->makeAssignment($mission, $provider);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/assignments/inbox')
            ->assertOk()
            ->assertJsonPath('data.0.client_name', 'Rene Magritte')
            ->assertJsonPath('data.0.address', '12 rue du Test')
            ->assertJsonPath('data.0.city', 'Bruxelles');
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
        return Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'address' => '12 rue du Test',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ]);
    }

    protected function makeAssignment(Mission $mission, User $provider): MissionAssignment
    {
        return MissionAssignment::factory()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);
    }
}
