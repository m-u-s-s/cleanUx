<?php

namespace Tests\Feature\Relations;

use App\Models\Booking;
use App\Models\BookingFavorite;
use App\Models\CustomerProfile;
use App\Models\Disponibilite;
use App\Models\ProviderProfile;
use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/** SP2 mobile parity — le chemin API booking (ClientBookingController::store + CreateBookingFromApiAction) doit : - persister provider_type_preference + preferred_provider_user_id - appliquer le gating premium via ProviderSelectionResolver (frontière sécurité) - le matching mobile doit honorer le type, ce que `CandidateFinderTest` atteste desormais sur le moteur reellement employe - /auth/me doit exposer is_premium */
class MobileBookingSelectionApiTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    private function standardClient(): User
    {
        $client = User::factory()->client()->create();
        CustomerProfile::query()->create([
            'user_id' => $client->id,
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);

        return $client->fresh();
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

    private function basePayload(ServiceCatalog $service, array $extra = []): array
    {
        return array_merge([
            'service_catalog_id' => $service->id,
            'address' => 'Rue de la Loi 1',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDays(3)->format('Y-m-d'),
            'scheduled_time' => '10:00',
        ], $extra);
    }

    #[Test]
    public function store_persists_provider_type_preference_and_favorite_provider(): void
    {
        $client = $this->standardClient();
        $favorite = User::factory()->create();

        BookingFavorite::query()->create([
            'client_user_id' => $client->id,
            'preferred_provider_user_id' => $favorite->id,
        ]);

        $service = ServiceCatalog::factory()->create(['is_active' => true]);

        Sanctum::actingAs($client);

        $response = $this->postJson('/api/client/bookings', $this->basePayload($service, [
            'provider_type_preference' => 'independent',
            'preferred_provider_user_id' => $favorite->id,
        ]))->assertCreated();

        $booking = Booking::findOrFail($response->json('data.id'));

        $this->assertSame('independent', $booking->provider_type_preference);
        $this->assertSame($favorite->id, (int) $booking->preferred_provider_user_id);
    }

    #[Test]
    public function store_rejects_new_provider_for_non_premium_client(): void
    {
        $client = $this->standardClient();
        $stranger = User::factory()->create();

        $service = ServiceCatalog::factory()->create(['is_active' => true]);

        Sanctum::actingAs($client);

        $this->postJson('/api/client/bookings', $this->basePayload($service, [
            'preferred_provider_user_id' => $stranger->id,
        ]))->assertForbidden();

        $this->assertDatabaseMissing('bookings', [
            'customer_user_id' => $client->id,
            'preferred_provider_user_id' => $stranger->id,
        ]);
    }

    #[Test]
    public function store_allows_new_provider_for_premium_client_and_persists(): void
    {
        $client = $this->premiumClient();
        $stranger = User::factory()->create();

        $service = ServiceCatalog::factory()->create(['is_active' => true]);

        Sanctum::actingAs($client);

        $response = $this->postJson('/api/client/bookings', $this->basePayload($service, [
            'provider_type_preference' => 'company',
            'preferred_provider_user_id' => $stranger->id,
        ]))->assertCreated();

        $booking = Booking::findOrFail($response->json('data.id'));

        $this->assertSame('company', $booking->provider_type_preference);
        $this->assertSame($stranger->id, (int) $booking->preferred_provider_user_id);
    }

    #[Test]
    public function store_defaults_to_any_when_no_preference_supplied(): void
    {
        $client = $this->standardClient();
        $service = ServiceCatalog::factory()->create(['is_active' => true]);

        Sanctum::actingAs($client);

        $response = $this->postJson('/api/client/bookings', $this->basePayload($service))
            ->assertCreated();

        $booking = Booking::findOrFail($response->json('data.id'));

        $this->assertSame('any', $booking->provider_type_preference);
        $this->assertNull($booking->preferred_provider_user_id);
    }

    #[Test]
    public function auth_me_exposes_is_premium_true_for_premium_client(): void
    {
        Sanctum::actingAs($this->premiumClient());

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('is_premium', true);
    }

    #[Test]
    public function auth_me_exposes_is_premium_false_for_standard_client(): void
    {
        Sanctum::actingAs($this->standardClient());

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('is_premium', false);
    }

    private function eligibleProvider(string $type, int $zoneId, string $date, ?int $orgId = null): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $zoneId,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $orgId,
            'provider_type' => $type,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        Disponibilite::create([
            'user_id' => $user->id,
            'date' => $date,
            'heure_debut' => '08:00:00',
            'heure_fin' => '18:00:00',
        ]);

        // LE MÉTIER EST OBLIGATOIRE : le filtre n'a plus de repli, et une réservation sans métier
        // résolvable ne rend AUCUN candidat plutôt que de les rendre tous.
        $user->trades()->syncWithoutDetaching([$this->matchingTrade()->id]);

        return $user;
    }

    private function matchingTrade(): Trade
    {
        return Trade::firstOrCreate(
            ['slug' => 'mobile-selection-trade'],
            ['code' => 'MST', 'name' => 'Nettoyage', 'is_active' => true, 'sort_order' => 1],
        );
    }
}
