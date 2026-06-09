<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\Promotions\ReferralsCenter;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReferralsCenterCoverageBatch9Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $referrer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->referrer = User::factory()->client()->create([
            'name' => 'Top Referrer',
            'referral_code' => 'TOPREF123',
        ]);
    }

    private function makeReferral(array $overrides = []): Referral
    {
        return Referral::factory()->create(array_merge([
            'referrer_user_id' => $this->referrer->id,
            'status' => Referral::STATUS_QUALIFIED,
        ], $overrides));
    }

    private function makeReward(Referral $referral, array $overrides = []): ReferralReward
    {
        return ReferralReward::create(array_merge([
            'referral_id' => $referral->id,
            'beneficiary_user_id' => $this->referrer->id,
            'role' => ReferralReward::ROLE_REFERRER,
            'reward_type' => ReferralReward::TYPE_CREDIT,
            'amount' => 10.00,
            'currency' => 'EUR',
            'status' => ReferralReward::STATUS_GRANTED,
            'granted_at' => now(),
            'metadata' => [],
        ], $overrides));
    }

    public function test_render_exposes_kpis_top_referrers_and_referrals(): void
    {
        $rewarded = $this->makeReferral(['status' => Referral::STATUS_REWARDED]);
        $this->makeReward($rewarded);
        $this->makeReferral(['status' => Referral::STATUS_QUALIFIED]);
        $this->makeReferral(['status' => Referral::STATUS_FRAUD]);

        Livewire::actingAs($this->admin)
            ->test(ReferralsCenter::class)
            ->assertOk()
            ->assertViewHas('kpis', function (array $kpis) {
                return $kpis['total_referrals'] === 3
                    && $kpis['qualified'] === 2
                    && $kpis['rewarded'] === 1
                    && $kpis['fraud'] === 1
                    && (float) $kpis['total_rewards_value'] === 10.0;
            })
            ->assertViewHas('topReferrers')
            ->assertViewHas('referrals');
    }

    public function test_top_referrers_includes_user_with_referral_code(): void
    {
        $this->makeReferral(['status' => Referral::STATUS_REWARDED]);

        Livewire::actingAs($this->admin)
            ->test(ReferralsCenter::class)
            ->assertOk()
            ->assertSee('Top Referrer');
    }

    public function test_flag_fraud_marks_referral_and_revokes_rewards(): void
    {
        $referral = $this->makeReferral(['status' => Referral::STATUS_QUALIFIED]);
        $reward = $this->makeReward($referral, ['status' => ReferralReward::STATUS_GRANTED]);

        Livewire::actingAs($this->admin)
            ->test(ReferralsCenter::class)
            ->call('flagFraud', $referral->id)
            ->assertDispatched('toast');

        $this->assertSame(Referral::STATUS_FRAUD, $referral->fresh()->status);

        $freshReward = $reward->fresh();
        $this->assertSame(ReferralReward::STATUS_REVOKED, $freshReward->status);
        $this->assertNotNull($freshReward->revoked_at);
        $this->assertSame('Marqué frauduleux par admin', $freshReward->revoked_reason);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'referral.flagged_fraud',
            'target_type' => Referral::class,
            'target_id' => $referral->id,
        ]);
    }

    public function test_filter_status_narrows_results(): void
    {
        $this->makeReferral(['status' => Referral::STATUS_FRAUD, 'referral_code' => 'FRAUDONE']);
        $this->makeReferral(['status' => Referral::STATUS_QUALIFIED, 'referral_code' => 'QUALONE']);

        Livewire::actingAs($this->admin)
            ->test(ReferralsCenter::class)
            ->set('filterStatus', Referral::STATUS_FRAUD)
            ->assertOk()
            ->assertSee('FRAUDONE')
            ->assertDontSee('QUALONE');
    }

    public function test_search_filters_by_referral_code(): void
    {
        $this->makeReferral(['referral_code' => 'FINDME99', 'referee_email' => 'a@example.com']);
        $this->makeReferral(['referral_code' => 'HIDDEN99', 'referee_email' => 'b@example.com']);

        Livewire::actingAs($this->admin)
            ->test(ReferralsCenter::class)
            ->set('search', 'FINDME')
            ->assertOk()
            ->assertSee('FINDME99')
            ->assertDontSee('HIDDEN99');
    }

    public function test_search_filters_by_referee_email(): void
    {
        $this->makeReferral(['referral_code' => 'CODEA01', 'referee_email' => 'unique-referee@example.com']);
        $this->makeReferral(['referral_code' => 'CODEB02', 'referee_email' => 'other@example.com']);

        Livewire::actingAs($this->admin)
            ->test(ReferralsCenter::class)
            ->set('search', 'unique-referee')
            ->assertOk()
            ->assertSee('CODEA01')
            ->assertDontSee('CODEB02');
    }
}
