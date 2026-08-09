<?php

namespace Tests\Feature;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Dispatch\AiDispatchService;
use App\Services\Matching\MatchingV2Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AiDispatchServiceFallbackCoverageBatch13Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Le métier commun aux fixtures — obligatoire depuis que le filtre n'a plus de repli.
     *
     * Une réservation sans métier résolvable ne rend AUCUN candidat, au lieu de les rendre tous :
     * c'est l'invariant « jamais un peintre en babysitting », et il vit dans le SQL.
     */
    protected function trade(): \App\Models\Trade
    {
        return \App\Models\Trade::firstOrCreate(
            ['slug' => 'ai-dispatch-fixture-trade'],
            ['code' => 'AIDF', 'name' => 'Nettoyage', 'is_active' => true, 'sort_order' => 1],
        );
    }

    protected function makeEmployee(?int $zoneId = null, bool $online = true): User
    {
        $user = User::factory()->employe()->create([
            'is_active' => true,
            'primary_service_zone_id' => $zoneId,
        ]);

        ProviderProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'provider_type' => ProviderType::INDEPENDENT->value,
                'status' => 'active',
                'verification_status' => 'verified',
                'is_online' => $online,
            ],
        );

        // EN LIGNE AU SENS DE PRESENCE V2 : le miroir binaire reste vrai quand l'application est
        // morte depuis vingt minutes, il ne décide donc plus rien.
        \App\Models\ProviderPresence::updateOrCreate(
            ['provider_user_id' => $user->id],
            ['status' => $online ? 'online' : 'offline', 'heartbeat_at' => now()],
        );

        $user->trades()->syncWithoutDetaching([$this->trade()->id]);

        return $user;
    }

    protected function makeClient(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);
    }

    protected function makeBooking(ServiceZone $zone, User $client, array $overrides = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'client_id' => $client->id,
            'service_zone_id' => $zone->id,
            'trade_id' => $this->trade()->id,
            'date' => now()->addDay()->toDateString(),
            'heure' => '10:00',
            'duree_estimee' => 90,
            'status' => 'en_attente',
        ], $overrides));
    }

    private function dispatcher(): AiDispatchService
    {
        return app(AiDispatchService::class);
    }

    public function test_matching_v2_throwing_falls_back_to_scorer_path(): void
    {
        config(['matching.enabled' => true, 'matching.shadow_mode' => false]);
        Log::spy();

        // Force MatchingV2 to blow up so the service drops into the
        // MatchingScorer fallback block (lines 41-71 of the source).
        $this->app->bind(MatchingV2Service::class, function () {
            return new class
            {
                public function bestFor(Booking $rdv): ?User
                {
                    throw new \RuntimeException('forced v2 failure');
                }
            };
        });

        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee($zone->id);
        $client = $this->makeClient();
        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);

        $best = $this->dispatcher()->bestEmployeeFor($rdv);

        $this->assertNotNull($best);
        $this->assertSame($employee->id, $best->id);
    }

    public function test_matching_v2_returning_null_falls_back_to_scorer_path(): void
    {
        config(['matching.enabled' => true, 'matching.shadow_mode' => false]);

        // MatchingV2 yields no candidate (not an exception): the source still
        // falls through to the MatchingScorer block.
        $this->app->bind(MatchingV2Service::class, function () {
            return new class
            {
                public function bestFor(Booking $rdv): ?User
                {
                    return null;
                }
            };
        });

        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee($zone->id);
        $client = $this->makeClient();
        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);

        $best = $this->dispatcher()->bestEmployeeFor($rdv);

        $this->assertNotNull($best);
        $this->assertSame($employee->id, $best->id);
    }

    public function test_scorer_fallback_returns_null_without_eligible_candidates(): void
    {
        config(['matching.enabled' => true, 'matching.shadow_mode' => false]);

        $this->app->bind(MatchingV2Service::class, function () {
            return new class
            {
                public function bestFor(Booking $rdv): ?User
                {
                    return null;
                }
            };
        });

        $zone = ServiceZone::factory()->create();
        $client = $this->makeClient();
        // No eligible employee created → scorer candidates empty → v1 empty.
        $rdv = $this->makeBooking($zone, $client)->fresh(['client', 'serviceZone']);

        $this->assertNull($this->dispatcher()->bestEmployeeFor($rdv));
    }

    public function test_rank_employees_empty_when_heure_missing(): void
    {
        $client = $this->makeClient();
        $zone = ServiceZone::factory()->create();
        $rdv = $this->makeBooking($zone, $client, ['heure' => null]);

        $ranking = $this->dispatcher()->rankEmployees($rdv->fresh());
        $this->assertCount(0, $ranking);
    }

    public function test_rank_employees_empty_when_date_missing(): void
    {
        $client = $this->makeClient();
        $zone = ServiceZone::factory()->create();
        $rdv = $this->makeBooking($zone, $client, ['date' => null]);

        $ranking = $this->dispatcher()->rankEmployees($rdv->fresh());
        $this->assertCount(0, $ranking);
    }

    public function test_favorite_score_is_zero_when_booking_has_no_client(): void
    {
        $zone = ServiceZone::factory()->create();
        $employee = $this->makeEmployee($zone->id);

        // Non-persisted booking with client_id = 0 exercises the early guard
        // in favoriteScore (clientId <= 0 → 0).
        $rdv = new Booking([
            'service_zone_id' => $zone->id,
            'client_id' => 0,
            'date' => now()->addDay()->toDateString(),
            'heure' => '10:00',
            'duree_estimee' => 90,
        ]);

        $details = $this->dispatcher()->scoreDetails($employee->fresh(), $rdv);
        $this->assertSame(0, $details['favorite']);
    }
}
