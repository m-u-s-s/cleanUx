<?php

namespace Tests\Feature\Marketing;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingCampaignStep;
use App\Models\MarketingSegment;
use App\Models\MarketingSegmentMember;
use App\Models\User;
use App\Services\Marketing\CampaignEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CampaignEngineCoverageBatch7Test extends TestCase
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
            'code' => 'seg_'.uniqid(),
            'name' => 'Batch7',
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

    protected function makeCampaign(?MarketingSegment $segment, array $steps): MarketingCampaign
    {
        $campaign = MarketingCampaign::create([
            'code' => 'camp_'.uniqid(),
            'name' => 'Batch7 campaign',
            'type' => MarketingCampaign::TYPE_DRIP_SEQUENCE,
            'status' => MarketingCampaign::STATUS_DRAFT,
            'segment_id' => $segment?->id,
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
                'content_overrides' => $stepDef['content_overrides'] ?? null,
                'is_active' => $stepDef['is_active'] ?? true,
            ]);
        }

        return $campaign;
    }

    public function test_schedule_returns_zero_without_segment(): void
    {
        $campaign = $this->makeCampaign(null, [['channel' => 'email']]);

        $this->assertSame(0, app(CampaignEngine::class)->schedule($campaign));
        $this->assertSame(0, MarketingCampaignRecipient::count());
    }

    public function test_schedule_returns_zero_without_active_steps(): void
    {
        $u = User::factory()->client()->create();
        $segment = $this->makeSegmentWith([$u]);
        $campaign = $this->makeCampaign($segment, [['channel' => 'email', 'is_active' => false]]);

        $this->assertSame(0, app(CampaignEngine::class)->schedule($campaign));
        $this->assertSame(0, MarketingCampaignRecipient::count());
    }

    public function test_schedule_returns_zero_with_empty_segment_but_marks_scheduled(): void
    {
        $segment = $this->makeSegmentWith([]);
        $campaign = $this->makeCampaign($segment, [['channel' => 'email']]);

        $this->assertSame(0, app(CampaignEngine::class)->schedule($campaign));
        $this->assertSame(MarketingCampaign::STATUS_SCHEDULED, $campaign->fresh()->status);
    }

    public function test_dispatch_due_recipients_sends_due_email_with_content_override(): void
    {
        $u = User::factory()->client()->create();
        $segment = $this->makeSegmentWith([$u]);
        $campaign = $this->makeCampaign($segment, [[
            'channel' => 'email',
            'content_overrides' => ['body' => 'Hi {name} {email} {variant}'],
        ]]);
        app(CampaignEngine::class)->schedule($campaign);

        MarketingCampaignRecipient::query()->update(['scheduled_for' => now()->subMinute()]);

        $sent = app(CampaignEngine::class)->dispatchDueRecipients();

        $this->assertSame(1, $sent);
        $this->assertSame(
            MarketingCampaignRecipient::STATUS_SENT,
            MarketingCampaignRecipient::first()->status,
        );
    }

    public function test_dispatch_due_recipients_marks_failed_on_unknown_channel(): void
    {
        $u = User::factory()->client()->create();
        $segment = $this->makeSegmentWith([$u]);
        $campaign = $this->makeCampaign($segment, [['channel' => 'fax']]);
        app(CampaignEngine::class)->schedule($campaign);

        MarketingCampaignRecipient::query()->update(['scheduled_for' => now()->subMinute()]);

        $sent = app(CampaignEngine::class)->dispatchDueRecipients();

        $this->assertSame(0, $sent);
        $recipient = MarketingCampaignRecipient::first();
        $this->assertSame(MarketingCampaignRecipient::STATUS_FAILED, $recipient->status);
        $this->assertNotNull($recipient->failed_at);
        $this->assertStringContainsString('fax', (string) $recipient->failed_reason);
    }

    public function test_dispatch_one_is_noop_when_not_queued(): void
    {
        $u = User::factory()->client()->create();
        $segment = $this->makeSegmentWith([$u]);
        $campaign = $this->makeCampaign($segment, [['channel' => 'email']]);
        app(CampaignEngine::class)->schedule($campaign);

        $recipient = MarketingCampaignRecipient::first();
        $recipient->forceFill(['status' => MarketingCampaignRecipient::STATUS_SENT])->save();

        app(CampaignEngine::class)->dispatchOne($recipient);

        $this->assertSame(MarketingCampaignRecipient::STATUS_SENT, $recipient->fresh()->status);
    }

    public function test_pause_and_resume_transition_status(): void
    {
        $segment = $this->makeSegmentWith([]);
        $campaign = $this->makeCampaign($segment, [['channel' => 'email']]);

        app(CampaignEngine::class)->pause($campaign);
        $this->assertSame(MarketingCampaign::STATUS_PAUSED, $campaign->fresh()->status);

        app(CampaignEngine::class)->resume($campaign);
        $this->assertSame(MarketingCampaign::STATUS_RUNNING, $campaign->fresh()->status);
    }

    public function test_assign_variant_returns_null_when_ab_disabled(): void
    {
        Config::set('marketing.ab_test_enabled', false);
        $u = User::factory()->client()->create();
        $campaign = MarketingCampaign::create([
            'code' => 'abdisabled',
            'name' => 'AB off',
            'type' => MarketingCampaign::TYPE_SINGLE_BLAST,
            'status' => MarketingCampaign::STATUS_DRAFT,
            'scheduled_at' => now(),
            'ab_test_config' => ['variants' => ['A', 'B']],
        ]);

        $this->assertNull(app(CampaignEngine::class)->assignVariant($campaign, $u));
    }

    public function test_assign_variant_returns_null_without_configured_variants(): void
    {
        $u = User::factory()->client()->create();
        $campaign = MarketingCampaign::create([
            'code' => 'novariants',
            'name' => 'No variants',
            'type' => MarketingCampaign::TYPE_SINGLE_BLAST,
            'status' => MarketingCampaign::STATUS_DRAFT,
            'scheduled_at' => now(),
        ]);

        $this->assertNull(app(CampaignEngine::class)->assignVariant($campaign, $u));
    }
}
