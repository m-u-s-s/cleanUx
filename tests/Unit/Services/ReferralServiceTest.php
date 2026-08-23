<?php

namespace Tests\Unit\Services;

use App\Models\Referral;
use App\Models\User;
use App\Services\Promotion\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReferralService;
    }

    // ─────────────────────────────────────────────────────────────────
    // generateCode / ensureReferralCode
    // ─────────────────────────────────────────────────────────────────

    public function test_ensure_referral_code_creates_unique_code_for_user(): void
    {
        $user = User::factory()->create(['referral_code' => null]);

        $code = $this->service->ensureReferralCode($user);

        $this->assertNotEmpty($code);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6,}$/', $code);
        $this->assertSame($code, $user->fresh()->referral_code);
    }

    public function test_ensure_referral_code_returns_existing_code_without_generating_new_one(): void
    {
        $user = User::factory()->create(['referral_code' => 'EXIST1234']);

        $code = $this->service->ensureReferralCode($user);

        $this->assertSame('EXIST1234', $code);
    }

    public function test_generated_codes_are_unique_across_users(): void
    {
        $users = User::factory()->count(5)->create(['referral_code' => null]);
        $codes = $users->map(fn ($u) => $this->service->ensureReferralCode($u));

        $this->assertSame($codes->count(), $codes->unique()->count(), 'All generated codes must be unique');
    }

    // ─────────────────────────────────────────────────────────────────
    // getShareData — localized message with code embedded
    // ─────────────────────────────────────────────────────────────────

    public function test_get_share_data_embeds_code_in_share_message(): void
    {
        config([
            'referral.sharing.message_template_fr' => 'Rejoins Brio avec mon code {code} !',
            'referral.landing_url_template' => '/register?ref={code}',
        ]);

        $user = User::factory()->create(['referral_code' => null]);
        $data = $this->service->getShareData($user, 'fr');

        $this->assertStringContainsString($data['referral_code'], $data['share_message']);
    }

    public function test_get_share_data_returns_correct_top_level_keys(): void
    {
        config([
            'referral.sharing.message_template_fr' => 'Code: {code}',
            'referral.landing_url_template' => '/register?ref={code}',
        ]);

        $user = User::factory()->create(['referral_code' => null]);
        $data = $this->service->getShareData($user, 'fr');

        $manquantes = array_values(array_filter(
            ['referral_code', 'invite_url', 'share_message', 'rewards', 'stats'],
            fn (string $k) => ! array_key_exists($k, $data),
        ));

        $this->assertSame([], $manquantes, 'Ces cles manquent au tableau de parrainage.');
    }

    public function test_get_share_data_falls_back_to_fr_template_for_unknown_locale(): void
    {
        config([
            'referral.sharing.message_template_fr' => 'Code FR: {code}',
            'referral.landing_url_template' => '/register?ref={code}',
        ]);

        $user = User::factory()->create(['referral_code' => null]);
        $data = $this->service->getShareData($user, 'xx'); // unknown locale

        $this->assertStringContainsString('Code FR:', $data['share_message']);
    }

    public function test_get_share_data_embeds_invite_url_in_message_when_template_uses_link_placeholder(): void
    {
        config([
            'referral.sharing.message_template_fr' => 'Inscription via {link}',
            'referral.landing_url_template' => '/register?ref={code}',
        ]);

        $user = User::factory()->create(['referral_code' => null]);
        $data = $this->service->getShareData($user, 'fr');

        $this->assertStringContainsString($data['invite_url'], $data['share_message']);
    }

    // ─────────────────────────────────────────────────────────────────
    // Self-referral prevention
    // ─────────────────────────────────────────────────────────────────

    public function test_self_referral_is_rejected_and_returns_null(): void
    {
        $user = User::factory()->create(['referral_code' => 'SELF123']);

        $result = $this->service->registerReferral('SELF123', $user);

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────────
    // Reward amounts come from config
    // ─────────────────────────────────────────────────────────────────

    public function test_reward_amounts_use_default_constants_when_config_is_zero(): void
    {
        config(['referral.rewards.referrer.amount' => 0, 'referral.rewards.referee.amount' => 0]);

        $referrer = User::factory()->create(['referral_code' => 'CONF0001']);
        $referee = User::factory()->create(['referral_code' => null]);

        $referral = $this->service->registerReferral('CONF0001', $referee);

        $this->assertNotNull($referral);
        $this->assertEquals(ReferralService::DEFAULT_REFERRER_REWARD, (float) $referral->referrer_reward_amount);
        $this->assertEquals(ReferralService::DEFAULT_REFEREE_REWARD, (float) $referral->referee_reward_amount);
    }

    public function test_reward_amounts_come_from_config_when_set(): void
    {
        // Config stores cents: 2000 = 20.00 EUR, 1500 = 15.00 EUR
        config(['referral.rewards.referrer.amount' => 2000, 'referral.rewards.referee.amount' => 1500]);

        $referrer = User::factory()->create(['referral_code' => 'CONF0002']);
        $referee = User::factory()->create(['referral_code' => null]);

        $referral = $this->service->registerReferral('CONF0002', $referee);

        $this->assertNotNull($referral);
        $this->assertEquals(20.00, (float) $referral->referrer_reward_amount);
        $this->assertEquals(15.00, (float) $referral->referee_reward_amount);
    }

    // ─────────────────────────────────────────────────────────────────
    // Expired referral codes
    // ─────────────────────────────────────────────────────────────────

    public function test_unknown_referral_code_returns_null(): void
    {
        $referee = User::factory()->create(['referral_code' => null]);

        $result = $this->service->registerReferral('DOESNOTEXIST', $referee);

        $this->assertNull($result);
    }

    public function test_expired_referral_is_not_reused_for_same_referee(): void
    {
        $referrer = User::factory()->create(['referral_code' => 'EXPI0001']);
        $referee = User::factory()->create(['referral_code' => null]);

        // Simulate an already-expired referral record for this referee
        Referral::create([
            'referrer_user_id' => $referrer->id,
            'referee_user_id' => $referee->id,
            'referee_email' => $referee->email,
            'referral_code' => 'EXPI0001',
            'status' => Referral::STATUS_EXPIRED,
            'invited_at' => now()->subDays(100),
            'signed_up_at' => now()->subDays(100),
            'expires_at' => now()->subDays(10),
            'referrer_reward_amount' => 15.00,
            'referee_reward_amount' => 10.00,
            'currency' => 'EUR',
        ]);

        // Since the only existing referral is expired, registerReferral should create a new one
        $result = $this->service->registerReferral('EXPI0001', $referee);

        $this->assertNotNull($result, 'An expired referral must not block re-registration');
        $this->assertSame(Referral::STATUS_SIGNED_UP, $result->status);
    }

    public function test_empty_code_string_returns_null(): void
    {
        $referee = User::factory()->create();

        $result = $this->service->registerReferral('   ', $referee);

        $this->assertNull($result);
    }
}
