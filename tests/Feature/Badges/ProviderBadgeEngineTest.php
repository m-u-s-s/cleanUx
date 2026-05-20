<?php

namespace Tests\Feature\Badges;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\ProviderBadge;
use App\Models\ProviderBadgeAward;
use App\Models\User;
use App\Services\Badges\ProviderBadgeEngine;
use Database\Seeders\ProviderBadgesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderBadgeEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProviderBadgesSeeder::class);
    }

    public function test_evaluate_awards_missions_count_badge_when_threshold_reached(): void
    {
        $provider = User::factory()->employe()->create();
        $client = User::factory()->client()->create();

        // 10 missions terminées → débloque rookie_10
        for ($i = 0; $i < 10; $i++) {
            Booking::factory()->create([
                'client_id' => $client->id,
                'employe_id' => $provider->id,
                'status' => 'termine',
            ]);
        }

        $awarded = app(ProviderBadgeEngine::class)->evaluate($provider);

        $rookieAwarded = ProviderBadgeAward::query()
            ->where('provider_user_id', $provider->id)
            ->whereHas('badge', fn ($q) => $q->where('code', 'rookie_10'))
            ->first();

        $this->assertNotNull($rookieAwarded);
        $this->assertSame(10, (int) $rookieAwarded->value_at_award);
    }

    public function test_evaluate_idempotent_no_double_award(): void
    {
        $provider = User::factory()->employe()->create();
        $client = User::factory()->client()->create();

        for ($i = 0; $i < 10; $i++) {
            Booking::factory()->create([
                'client_id' => $client->id,
                'employe_id' => $provider->id,
                'status' => 'termine',
            ]);
        }

        app(ProviderBadgeEngine::class)->evaluate($provider);
        app(ProviderBadgeEngine::class)->evaluate($provider);   // Run again

        $count = ProviderBadgeAward::query()
            ->where('provider_user_id', $provider->id)
            ->whereHas('badge', fn ($q) => $q->where('code', 'rookie_10'))
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_evaluate_does_not_award_below_threshold(): void
    {
        $provider = User::factory()->employe()->create();
        $client = User::factory()->client()->create();

        // Seulement 5 missions → ne déclenche pas rookie_10
        for ($i = 0; $i < 5; $i++) {
            Booking::factory()->create([
                'client_id' => $client->id,
                'employe_id' => $provider->id,
                'status' => 'termine',
            ]);
        }

        app(ProviderBadgeEngine::class)->evaluate($provider);

        $this->assertSame(0, ProviderBadgeAward::query()->where('provider_user_id', $provider->id)->count());
    }

    public function test_rating_avg_badge_awarded(): void
    {
        $provider = User::factory()->employe()->create();
        $client = User::factory()->client()->create();

        // 5 feedbacks 4★ avec booking factice → moyenne 4.0 → débloque good_rated
        for ($i = 0; $i < 5; $i++) {
            $booking = Booking::factory()->create([
                'client_id' => $client->id,
                'employe_id' => $provider->id,
                'status' => 'termine',
            ]);
            Feedback::query()->create([
                'booking_id' => $booking->id,
                'rendez_vous_id' => $booking->id,
                'client_id' => $client->id,
                'employe_id' => $provider->id,
                'direction' => Feedback::DIRECTION_CLIENT_TO_PROVIDER,
                'rating' => 4,
                'note' => 4,
            ]);
        }

        app(ProviderBadgeEngine::class)->evaluate($provider);

        $good = ProviderBadgeAward::query()
            ->where('provider_user_id', $provider->id)
            ->whereHas('badge', fn ($q) => $q->where('code', 'good_rated'))
            ->first();

        $this->assertNotNull($good);
    }

    public function test_streak_five_stars_badge(): void
    {
        $provider = User::factory()->employe()->create();
        $client = User::factory()->client()->create();

        // 12 feedbacks consécutifs 5★ → débloque streak_10
        for ($i = 0; $i < 12; $i++) {
            $booking = Booking::factory()->create([
                'client_id' => $client->id,
                'employe_id' => $provider->id,
                'status' => 'termine',
            ]);
            Feedback::query()->create([
                'booking_id' => $booking->id,
                'rendez_vous_id' => $booking->id,
                'client_id' => $client->id,
                'employe_id' => $provider->id,
                'direction' => Feedback::DIRECTION_CLIENT_TO_PROVIDER,
                'rating' => 5,
                'note' => 5,
            ]);
        }

        app(ProviderBadgeEngine::class)->evaluate($provider);

        $streak = ProviderBadgeAward::query()
            ->where('provider_user_id', $provider->id)
            ->whereHas('badge', fn ($q) => $q->where('code', 'streak_10'))
            ->first();

        $this->assertNotNull($streak);
    }
}
