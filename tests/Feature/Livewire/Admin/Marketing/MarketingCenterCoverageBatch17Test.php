<?php

namespace Tests\Feature\Livewire\Admin\Marketing;

use App\Livewire\Admin\Marketing\MarketingCenter;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingCampaignStep;
use App\Models\MarketingOptOut;
use App\Models\MarketingSegment;
use App\Models\MarketingSegmentMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingCenterCoverageBatch17Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('marketing.enabled', true);
        Config::set('marketing.ab_test_enabled', true);

        $this->admin = User::factory()->admin()->create();
    }

    protected function seedSegment(bool $active = true, ?string $name = null): MarketingSegment
    {
        return MarketingSegment::create([
            'code' => 'seg_'.uniqid(),
            'name' => $name ?? 'Segment '.uniqid(),
            'rules' => [],
            'is_active' => $active,
        ]);
    }

    protected function seedCampaignWithMembers(int $members): MarketingCampaign
    {
        $segment = $this->seedSegment();
        for ($i = 0; $i < $members; $i++) {
            $u = User::factory()->client()->create();
            MarketingSegmentMember::create([
                'segment_id' => $segment->id,
                'user_id' => $u->id,
                'computed_at' => now(),
            ]);
        }
        $segment->forceFill(['member_count' => $members])->save();

        $campaign = MarketingCampaign::create([
            'code' => 'camp_'.uniqid(),
            'name' => 'Campaign '.uniqid(),
            'type' => MarketingCampaign::TYPE_SINGLE_BLAST,
            'status' => MarketingCampaign::STATUS_DRAFT,
            'segment_id' => $segment->id,
            'scheduled_at' => now(),
        ]);

        MarketingCampaignStep::create([
            'campaign_id' => $campaign->id,
            'position' => 1,
            'delay_minutes' => 0,
            'channel' => 'email',
            'subject' => 'Hi',
            'template_code' => 'demo',
            'is_active' => true,
        ]);

        return $campaign;
    }

    public function test_render_segments_tab_shows_kpis_and_items(): void
    {
        $this->seedSegment(true, 'Alpha Active Segment');
        $this->seedSegment(false, 'Beta Inactive Segment');

        Livewire::actingAs($this->admin)
            ->test(MarketingCenter::class)
            ->assertOk()
            ->assertSet('tab', 'segments')
            ->assertViewHas('kpis')
            ->assertViewHas('items')
            ->assertSee('Alpha Active Segment');
    }

    public function test_segments_tab_search_filters_by_name(): void
    {
        $this->seedSegment(true, 'Loyal Clients');
        $this->seedSegment(true, 'Dormant Users');

        Livewire::actingAs($this->admin)
            ->test(MarketingCenter::class)
            ->set('search', 'Loyal')
            ->assertSee('Loyal Clients')
            ->assertDontSee('Dormant Users');
    }

    public function test_campaigns_tab_renders_with_search(): void
    {
        $campaign = $this->seedCampaignWithMembers(0);
        $campaign->forceFill(['name' => 'Spring Blast'])->save();

        Livewire::actingAs($this->admin)
            ->test(MarketingCenter::class)
            ->set('tab', 'campaigns')
            ->assertOk()
            ->assertViewHas('items')
            ->assertSee('Spring Blast')
            ->set('search', 'Spring')
            ->assertSee('Spring Blast');
    }

    public function test_recipients_tab_renders_and_filters_by_email(): void
    {
        $campaign = $this->seedCampaignWithMembers(0);
        $step = $campaign->steps()->first();
        $matching = User::factory()->client()->create(['email' => 'match@example.test']);
        $other = User::factory()->client()->create(['email' => 'nope@example.test']);

        foreach ([$matching, $other] as $u) {
            MarketingCampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'step_id' => $step->id,
                'user_id' => $u->id,
                'channel' => 'email',
                'status' => MarketingCampaignRecipient::STATUS_QUEUED,
                'idempotency_key' => 'k_'.$u->id,
                'scheduled_for' => now(),
            ]);
        }

        Livewire::actingAs($this->admin)
            ->test(MarketingCenter::class)
            ->set('tab', 'recipients')
            ->assertOk()
            ->assertViewHas('items')
            ->set('search', 'match@example.test')
            ->assertOk();
    }

    public function test_recompute_segment_dispatches_toast(): void
    {
        $segment = $this->seedSegment(true);
        $u = User::factory()->client()->create();
        MarketingSegmentMember::create([
            'segment_id' => $segment->id,
            'user_id' => $u->id,
            'computed_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(MarketingCenter::class)
            ->call('recomputeSegment', $segment->id)
            ->assertDispatched('toast');
    }

    public function test_schedule_campaign_dispatches_success_toast(): void
    {
        $campaign = $this->seedCampaignWithMembers(2);

        Livewire::actingAs($this->admin)
            ->test(MarketingCenter::class)
            ->call('scheduleCampaign', $campaign->id)
            ->assertDispatched('toast');

        $this->assertSame(
            MarketingCampaign::STATUS_SCHEDULED,
            $campaign->fresh()->status,
        );
        $this->assertGreaterThan(0, MarketingCampaignRecipient::count());
    }

    public function test_schedule_campaign_with_empty_segment_still_dispatches_toast(): void
    {
        $campaign = $this->seedCampaignWithMembers(0);

        Livewire::actingAs($this->admin)
            ->test(MarketingCenter::class)
            ->call('scheduleCampaign', $campaign->id)
            ->assertDispatched('toast');
    }

    public function test_pause_campaign_sets_paused_and_toasts(): void
    {
        $campaign = $this->seedCampaignWithMembers(0);
        $campaign->forceFill(['status' => MarketingCampaign::STATUS_RUNNING])->save();

        Livewire::actingAs($this->admin)
            ->test(MarketingCenter::class)
            ->call('pauseCampaign', $campaign->id)
            ->assertDispatched('toast');

        $this->assertSame(MarketingCampaign::STATUS_PAUSED, $campaign->fresh()->status);
    }

    public function test_cancel_campaign_sets_cancelled_and_toasts(): void
    {
        $campaign = $this->seedCampaignWithMembers(0);
        $campaign->forceFill(['status' => MarketingCampaign::STATUS_RUNNING])->save();

        Livewire::actingAs($this->admin)
            ->test(MarketingCenter::class)
            ->call('cancelCampaign', $campaign->id)
            ->assertDispatched('toast');

        $this->assertSame(MarketingCampaign::STATUS_CANCELLED, $campaign->fresh()->status);
    }

    public function test_kpis_count_opt_outs_and_running_campaigns(): void
    {
        $campaign = $this->seedCampaignWithMembers(0);
        $campaign->forceFill(['status' => MarketingCampaign::STATUS_RUNNING])->save();

        $u = User::factory()->client()->create();
        MarketingOptOut::create([
            'user_id' => $u->id,
            'channel' => MarketingOptOut::CHANNEL_ALL,
            'opted_out_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(MarketingCenter::class)
            ->assertOk()
            ->assertViewHas('kpis', fn ($kpis) => $kpis['opt_outs_total'] === 1 && $kpis['campaigns_running'] >= 1);
    }
}
