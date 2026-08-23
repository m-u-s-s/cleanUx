<?php

namespace Tests\Feature\Onboarding;

use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Zones d'intervention du prestataire. */
class ProviderServiceZonesTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTE = '/api/provider/onboarding/service-zones';

    public function test_it_lists_the_bookable_zones(): void
    {
        $this->zone('Zone Bruxelles', bookable: true, visible: true);

        $this->actingAs($this->provider(), 'sanctum')->getJson(self::ROUTE)
            ->assertOk()
            ->assertJsonCount(1, 'zones')
            ->assertJsonPath('zones.0.name', 'Zone Bruxelles');
    }

    /** Proposer une zone fermée laisserait un prestataire s'y positionner sans jamais y recevoir la moindre mission. */
    public function test_a_closed_zone_is_not_offered(): void
    {
        $this->zone('Zone ouverte', bookable: true, visible: true);
        $this->zone('Zone fermée', bookable: false, visible: true);
        $this->zone('Zone masquée', bookable: true, visible: false);

        $this->actingAs($this->provider(), 'sanctum')->getJson(self::ROUTE)
            ->assertOk()
            ->assertJsonCount(1, 'zones')
            ->assertJsonPath('zones.0.name', 'Zone ouverte');
    }

    /** La route sert pendant l'onboarding : elle doit rester ouverte avant approbation. */
    public function test_a_provider_awaiting_approval_can_reach_it(): void
    {
        $this->zone('Zone Bruxelles', bookable: true, visible: true);

        $user = $this->provider();
        ProviderProfile::where('user_id', $user->id)->first()
            ?->forceFill(['self_registered_at' => now()])->save();

        $this->actingAs($user, 'sanctum')->getJson(self::ROUTE)->assertOk();
    }

    /** Les zones choisies doivent être réellement rattachées au prestataire. */
    public function test_selected_zones_are_persisted(): void
    {
        $zone = $this->zone('Zone Bruxelles', bookable: true, visible: true);
        $user = $this->provider();

        $this->actingAs($user, 'sanctum')->postJson('/api/provider/onboarding/skills', [
            'skills' => ['nettoyage'],
            'service_zone_ids' => [$zone->id],
        ])->assertOk();

        $this->assertDatabaseHas('employee_zone_assignments', [
            'user_id' => $user->id,
            'service_zone_id' => $zone->id,
        ]);
    }

    public function test_an_unknown_zone_is_rejected(): void
    {
        $this->actingAs($this->provider(), 'sanctum')->postJson('/api/provider/onboarding/skills', [
            'skills' => ['nettoyage'],
            'service_zone_ids' => [99999],
        ])->assertStatus(422)->assertJsonValidationErrors('service_zone_ids.0');
    }

    private function provider(): User
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'individual',
            'status' => 'active',
            'verification_status' => 'unverified',
        ]);

        return $user;
    }

    private function zone(string $name, bool $bookable, bool $visible): ServiceZone
    {
        $countryId = DB::table('countries')->value('id')
            ?? DB::table('countries')->insertGetId([
                'name' => 'Belgique',
                'iso_code' => 'BE',
                'iso3_code' => 'BEL',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return ServiceZone::query()->create([
            'country_id' => $countryId,
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'status' => 'active',
            'is_bookable' => $bookable,
            'is_visible' => $visible,
            'priority' => 10,
        ]);
    }
}
