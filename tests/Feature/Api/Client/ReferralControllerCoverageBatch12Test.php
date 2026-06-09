<?php

namespace Tests\Feature\Api\Client;

use App\Models\Referral;
use App\Models\User;
use App\Services\Promotion\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralControllerCoverageBatch12Test extends TestCase
{
    use RefreshDatabase;

    public function test_me_requires_auth(): void
    {
        $this->getJson('/api/client/referrals/me')->assertUnauthorized();
    }

    public function test_me_returns_code_invite_url_stats_and_rewards(): void
    {
        $me = User::factory()->create(['role' => 'client']);

        // Mix of statuses to exercise the stats aggregation paths.
        Referral::factory()->create(['referrer_user_id' => $me->id, 'status' => Referral::STATUS_INVITED]);
        Referral::factory()->create(['referrer_user_id' => $me->id, 'status' => Referral::STATUS_SIGNED_UP]);
        Referral::factory()->create(['referrer_user_id' => $me->id, 'status' => Referral::STATUS_QUALIFIED]);
        Referral::factory()->create(['referrer_user_id' => $me->id, 'status' => Referral::STATUS_REWARDED]);

        Sanctum::actingAs($me);

        $json = $this->getJson('/api/client/referrals/me')
            ->assertOk()
            ->assertJsonStructure([
                'referral_code',
                'invite_url',
                'stats' => [
                    'total_invited',
                    'total_signed_up',
                    'total_qualified',
                    'total_rewarded',
                    'total_earned',
                ],
                'rewards' => [
                    'per_qualified_referrer',
                    'per_qualified_referee',
                    'currency',
                ],
            ])
            ->json();

        $this->assertSame(4, $json['stats']['total_invited']);
        $this->assertSame(3, $json['stats']['total_signed_up']);
        $this->assertSame(2, $json['stats']['total_qualified']);
        $this->assertSame(1, $json['stats']['total_rewarded']);
        $this->assertSame('EUR', $json['rewards']['currency']);
        $this->assertEquals(ReferralService::DEFAULT_REFERRER_REWARD, $json['rewards']['per_qualified_referrer']);
        $this->assertEquals(ReferralService::DEFAULT_REFEREE_REWARD, $json['rewards']['per_qualified_referee']);
        $this->assertStringContainsString('ref=', $json['invite_url']);
        $this->assertNotEmpty($json['referral_code']);
    }

    public function test_invite_requires_auth(): void
    {
        $this->postJson('/api/client/referrals/invite', ['email' => 'a@b.test'])->assertUnauthorized();
    }

    public function test_invite_validates_email(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($me);

        $this->postJson('/api/client/referrals/invite', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_invite_creates_referral_sends_mail_and_returns_201(): void
    {
        Mail::fake();

        $me = User::factory()->create(['role' => 'client', 'name' => 'Alice Referrer']);
        Sanctum::actingAs($me);

        $json = $this->postJson('/api/client/referrals/invite', [
            'email' => 'friend@example.test',
            'message' => 'Join me on CleanUx!',
        ])
            ->assertStatus(201)
            ->assertJsonStructure(['referral_id', 'invite_url', 'expires_at'])
            ->json();

        $this->assertDatabaseHas('referrals', [
            'id' => $json['referral_id'],
            'referrer_user_id' => $me->id,
            'referee_email' => 'friend@example.test',
            'status' => Referral::STATUS_INVITED,
            'source_channel' => 'api_invite',
        ]);

        $this->assertStringContainsString('ref=', $json['invite_url']);
    }

    public function test_list_requires_auth(): void
    {
        $this->getJson('/api/client/referrals')->assertUnauthorized();
    }

    public function test_list_returns_only_own_referrals_with_fields(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $other = User::factory()->create(['role' => 'client']);

        $mine = Referral::factory()->create(['referrer_user_id' => $me->id]);
        $theirs = Referral::factory()->create(['referrer_user_id' => $other->id]);

        Sanctum::actingAs($me);

        $data = $this->getJson('/api/client/referrals')
            ->assertOk()
            ->assertJsonStructure(['data' => [[
                'id',
                'referee_email',
                'referee_name',
                'status',
                'invited_at',
                'signed_up_at',
                'qualified_at',
                'rewarded_at',
                'referrer_reward_amount',
                'currency',
            ]]])
            ->json('data');

        $ids = collect($data)->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id), 'cross-referrer leak');
    }

    public function test_list_respects_limit_parameter(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        Referral::factory()->count(3)->create(['referrer_user_id' => $me->id]);

        Sanctum::actingAs($me);

        $data = $this->getJson('/api/client/referrals?limit=2')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $data);
    }
}
