<?php

namespace Tests\Feature\Promotion;

use App\Models\Referral;
use App\Models\User;
use App\Services\Promotion\ReferralService;
use App\Services\Promotion\ReferralViralTierEngine;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReferralV2Test extends TestCase
{
    use RefreshDatabase;

    // ─── API endpoint tests ────────────────────────────────────────────────

    public function test_my_code_returns_referral_code_and_share_message(): void
    {
        $user = User::factory()->client()->create(['locale' => 'fr']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/client/referral/my-code');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'referral_code',
                    'invite_url',
                    'share_message',
                    'rewards',
                    'stats',
                    'current_tier',
                    'next_tier',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.referral_code'));
        $this->assertStringContainsString(
            $response->json('data.referral_code'),
            $response->json('data.invite_url'),
        );
    }

    public function test_my_code_is_idempotent(): void
    {
        $user = User::factory()->client()->create();

        $first = $this->actingAs($user, 'sanctum')
            ->getJson('/api/client/referral/my-code')
            ->json('data.referral_code');

        $second = $this->actingAs($user, 'sanctum')
            ->getJson('/api/client/referral/my-code')
            ->json('data.referral_code');

        $this->assertSame($first, $second);
    }

    public function test_stats_returns_aggregate_counts(): void
    {
        $referrer = User::factory()->client()->create();
        $service = app(ReferralService::class);
        $code = $service->ensureReferralCode($referrer);

        $referee1 = User::factory()->client()->create();
        $service->registerReferral($code, $referee1);

        $response = $this->actingAs($referrer->fresh(), 'sanctum')
            ->getJson('/api/client/referral/stats');

        $response->assertOk()
            ->assertJsonPath('data.stats.total_signed_up', 1);
    }

    public function test_share_returns_localised_message(): void
    {
        $user = User::factory()->client()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/client/referral/share', ['locale' => 'nl']);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['referral_code', 'invite_url', 'share_message'],
            ]);

        $this->assertStringContainsString(
            'CleanUx',
            $response->json('data.share_message'),
        );
    }

    public function test_share_rejects_invalid_locale(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/client/referral/share', ['locale' => 'de'])
            ->assertUnprocessable();
    }

    // ─── Registration hook tests ───────────────────────────────────────────

    public function test_register_applies_referral_code_from_request(): void
    {
        $referrer = User::factory()->client()->create();
        $code = app(ReferralService::class)->ensureReferralCode($referrer);

        $response = $this->postJson('/api/auth/register', [
            'name'             => 'New User',
            'email'            => 'newuser@example.com',
            'password'         => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms'     => '1',
            'referral_code'    => $code,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('referrals', [
            'referrer_user_id' => $referrer->id,
            'referral_code'    => strtoupper($code),
            'status'           => Referral::STATUS_SIGNED_UP,
        ]);
    }

    public function test_register_with_invalid_referral_code_does_not_fail(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'             => 'Another User',
            'email'            => 'another@example.com',
            'password'         => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms'     => '1',
            'referral_code'    => 'INVALID-CODE-XYZ',
        ]);

        // Registration must succeed even with a bogus code
        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'another@example.com']);
    }

    public function test_register_without_referral_code_is_unaffected(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'             => 'Plain User',
            'email'            => 'plain@example.com',
            'password'         => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms'     => '1',
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('referrals', 0);
    }

    // ─── Config-driven reward amounts ─────────────────────────────────────

    public function test_referral_registration_uses_config_reward_amounts(): void
    {
        Config::set('referral.rewards.referrer.amount', 2000); // €20
        Config::set('referral.rewards.referee.amount',  2500); // €25

        $referrer = User::factory()->client()->create();
        $referee  = User::factory()->client()->create();

        $service = app(ReferralService::class);
        $code = $service->ensureReferralCode($referrer);
        $referral = $service->registerReferral($code, $referee);

        $this->assertNotNull($referral);
        $this->assertEquals(20.00, (float) $referral->referrer_reward_amount);
        $this->assertEquals(25.00, (float) $referral->referee_reward_amount);
    }

    // ─── Viral tier engine is called after qualification ──────────────────

    public function test_viral_tier_engine_called_after_qualification(): void
    {
        $tierEngineCalled = false;
        $this->app->bind(ReferralViralTierEngine::class, function () use (&$tierEngineCalled) {
            return new class($tierEngineCalled) extends ReferralViralTierEngine {
                public function __construct(private bool &$called) {}

                public function checkAndAwardTier(User $referrer): array
                {
                    $this->called = true;
                    return [];
                }
            };
        });

        $referrer = User::factory()->client()->create();
        $referee  = User::factory()->client()->create();

        $service = app(ReferralService::class);
        $code = $service->ensureReferralCode($referrer);
        $service->registerReferral($code, $referee);

        $booking = Booking::create([
            'client_id'    => $referee->id,
            'date'         => now()->subDay(),
            'heure'        => '10:00',
            'status'       => 'termine',
            'devis_estime' => 100,
        ]);

        $service->markQualifiedByBooking($booking->fresh());

        $this->assertTrue($tierEngineCalled, 'ReferralViralTierEngine::checkAndAwardTier was not called');
    }

    // ─── getShareData locale fallback ─────────────────────────────────────

    public function test_get_share_data_falls_back_to_fr_for_unsupported_locale(): void
    {
        $user = User::factory()->client()->create(['locale' => 'es']);
        $data = app(ReferralService::class)->getShareData($user, 'es');

        // Should fall back to fr template — message must still contain the code
        $this->assertStringContainsString($data['referral_code'], $data['share_message']);
    }
}
