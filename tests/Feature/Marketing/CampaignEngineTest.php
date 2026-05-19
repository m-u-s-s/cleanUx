<?php

namespace Tests\Feature\Marketing;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingCampaignStep;
use App\Models\MarketingSegment;
use App\Models\MarketingSegmentMember;
use App\Models\User;
use App\Services\Marketing\CampaignEngine;
use App\Services\Marketing\OptOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CampaignEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('marketing.enabled', true);
        Config::set('marketing.ab_test_enabled', true);
    }

    protected function makeSegmentWith(array $users): MarketingSegment
    {
        $segment = MarketingSegment::create([
            'code' => 'seg_' . uniqid(),
            'name' => 'Test',
            'rules' => [],
            'is_active' => true,
        ]);
        foreach ($users as $u) {
            MarketingSegmentMember::create([
                'segment_id' => $segment->id,
                'user_id' => $u->id,
                'computed_at' => now(),
            ]);
        }
        $segment->forceFill(['member_count' => count($users)])->save();
        return $segment;
    }

    protected function makeCampaign(MarketingSegment $segment, array $steps): MarketingCampaign
    {
        $campaign = MarketingCampaign::create([
            'code' => 'camp_' . uniqid(),
            'name' => 'Test campaign',
            'type' => MarketingCampaign::TYPE_DRIP_SEQUENCE,
            'status' => MarketingCampaign::STATUS_DRAFT,
            'segment_id' => $segment->id,
            'scheduled_at' => now(),
        ]);
        foreach ($steps as $i => $stepDef) {
            MarketingCampaignStep::create([
                'campaign_id' => $campaign->id,
                'position' => $i + 1,
                'delay_minutes' => $stepDef['delay_minutes'] ?? 0,
                'channel' => $stepDef['channel'] ?? 'email',
                'subject' => $stepDef['subject'] ?? 'Hi',
                'template_code' => 'demo',
                'variant_label' => $stepDef['variant'] ?? null,
                'is_active' => true,
            ]);
        }
        return $campaign;
    }

    public function test_schedule_creates_one_recipient_per_member_per_step(): void
    {
        $u1 = User::factory()->client()->create();
        $u2 = User::factory()->client()->create();
        $segment = $this->makeSegmentWith([$u1, $u2]);
        $campaign = $this->makeCampaign($segment, [
            ['channel' => 'email'],
            ['channel' => 'push', 'delay_minutes' => 60],
        ]);

        $created = app(CampaignEngine::class)->schedule($campaign);

        $this->assertSame(4, $created);
        $this->assertSame(4, MarketingCampaignRecipient::count());
        $this->assertSame(MarketingCampaign::STATUS_SCHEDULED, $campaign->fresh()->status);
    }

    public function test_schedule_marks_opted_out_recipients(): void
    {
        $u = User::factory()->client()->create();
        $segment = $this->makeSegmentWith([$u]);
        app(OptOutService::class)->optOut($u, 'email');

        $campaign = $this->makeCampaign($segment, [['channel' => 'email']]);

        app(CampaignEngine::class)->schedule($campaign);

        $recipient = MarketingCampaignRecipient::first();
        $this->assertSame(MarketingCampaignRecipient::STATUS_OPTED_OUT, $recipient->status);
    }

    public function test_schedule_drip_sequences_increments_scheduled_for(): void
    {
        $u = User::factory()->client()->create();
        $segment = $this->makeSegmentWith([$u]);
        $campaign = $this->makeCampaign($segment, [
            ['channel' => 'email', 'delay_minutes' => 0],
            ['channel' => 'email', 'delay_minutes' => 60],
            ['channel' => 'email', 'delay_minutes' => 1440],
        ]);

        app(CampaignEngine::class)->schedule($campaign);

        $recipients = MarketingCampaignRecipient::orderBy('scheduled_for')->get();
        $this->assertCount(3, $recipients);
        $this->assertEqualsWithDelta(0, $recipients[0]->scheduled_for->diffInMinutes(now()), 1);
        $this->assertEqualsWithDelta(60, $recipients[1]->scheduled_for->diffInMinutes(now()), 1);
        $this->assertEqualsWithDelta(1500, $recipients[2]->scheduled_for->diffInMinutes(now()), 1);
    }

    public function test_schedule_assigns_ab_variant_deterministically(): void
    {
        Config::set('marketing.ab_test_enabled', true);

        $u = User::factory()->client()->create();
        $segment = $this->makeSegmentWith([$u]);

        $campaign = MarketingCampaign::create([
            'code' => 'abtest',
            'name' => 'AB',
            'type' => MarketingCampaign::TYPE_SINGLE_BLAST,
            'status' => MarketingCampaign::STATUS_DRAFT,
            'segment_id' => $segment->id,
            'scheduled_at' => now(),
            'ab_test_config' => ['variants' => ['A', 'B']],
        ]);
        MarketingCampaignStep::create([
            'campaign_id' => $campaign->id,
            'position' => 1, 'delay_minutes' => 0,
            'channel' => 'email', 'is_active' => true,
        ]);

        app(CampaignEngine::class)->schedule($campaign);

        $r = MarketingCampaignRecipient::first();
        $this->assertContains($r->variant_label, ['A', 'B']);

        // Same user + same campaign code = same variant (deterministic)
        $variant1 = app(CampaignEngine::class)->assignVariant($campaign, $u);
        $variant2 = app(CampaignEngine::class)->assignVariant($campaign, $u);
        $this->assertSame($variant1, $variant2);
    }

    public function test_dispatch_one_sends_email_and_marks_sent(): void
    {
        $u = User::factory()->client()->create();
        $segment = $this->makeSegmentWith([$u]);
        $campaign = $this->makeCampaign($segment, [['channel' => 'email']]);
        app(CampaignEngine::class)->schedule($campaign);

        $recipient = MarketingCampaignRecipient::first();
        $recipient->forceFill(['scheduled_for' => now()->subMinute()])->save();

        app(CampaignEngine::class)->dispatchOne($recipient);

        $recipient->refresh();
        $this->assertSame(MarketingCampaignRecipient::STATUS_SENT, $recipient->status);
        $this->assertNotNull($recipient->sent_at);
    }

    public function test_dispatch_re_checks_opt_out_at_send_time(): void
    {
        $u = User::factory()->client()->create();
        $segment = $this->makeSegmentWith([$u]);
        $campaign = $this->makeCampaign($segment, [['channel' => 'email']]);
        app(CampaignEngine::class)->schedule($campaign);

        // User opts out AFTER scheduling
        app(OptOutService::class)->optOut($u, 'email');

        $recipient = MarketingCampaignRecipient::first();
        $recipient->forceFill(['scheduled_for' => now()->subMinute()])->save();

        app(CampaignEngine::class)->dispatchOne($recipient);

        $recipient->refresh();
        $this->assertSame(MarketingCampaignRecipient::STATUS_OPTED_OUT, $recipient->status);
    }

    public function test_cancel_marks_queued_recipients_skipped(): void
    {
        $u = User::factory()->client()->create();
        $segment = $this->makeSegmentWith([$u]);
        $campaign = $this->makeCampaign($segment, [['channel' => 'email'], ['channel' => 'email', 'delay_minutes' => 60]]);
        app(CampaignEngine::class)->schedule($campaign);

        app(CampaignEngine::class)->cancel($campaign);

        $this->assertSame(MarketingCampaign::STATUS_CANCELLED, $campaign->fresh()->status);
        $this->assertSame(0, MarketingCampaignRecipient::query()
            ->where('status', MarketingCampaignRecipient::STATUS_QUEUED)->count());
        $this->assertSame(2, MarketingCampaignRecipient::query()
            ->where('status', MarketingCampaignRecipient::STATUS_SKIPPED)->count());
    }
}
