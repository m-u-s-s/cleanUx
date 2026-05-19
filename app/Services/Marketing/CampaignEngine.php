<?php

namespace App\Services\Marketing;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingCampaignStep;
use App\Models\MarketingSegmentMember;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CampaignEngine — pilote la vie d'une campagne marketing.
 *
 *   - schedule(campaign) : matérialise un MarketingCampaignRecipient par membre×step,
 *     skippe les opt-outs immédiatement (status=opted_out).
 *   - dispatchDueRecipients() : envoie les recipients prêts (scheduled_for <= now)
 *     via le canal approprié. À appeler par scheduler ou queue worker.
 *   - assignVariant(campaign, user) : choisit déterministiquement un variant_label
 *     parmi ab_test_config.variants si A/B activé.
 *   - markStatus(recipient, status, ...) : transitions du recipient.
 */
class CampaignEngine
{
    public function __construct(protected OptOutService $optOut)
    {
    }

    public function schedule(MarketingCampaign $campaign): int
    {
        if (! $campaign->segment_id) {
            return 0;
        }

        $steps = $campaign->steps()->where('is_active', true)->orderBy('position')->get();
        if ($steps->isEmpty()) {
            return 0;
        }

        $memberIds = MarketingSegmentMember::query()
            ->where('segment_id', $campaign->segment_id)
            ->pluck('user_id')
            ->all();

        if (empty($memberIds)) {
            $campaign->forceFill([
                'status' => MarketingCampaign::STATUS_SCHEDULED,
                'scheduled_at' => $campaign->scheduled_at ?? now(),
            ])->save();
            return 0;
        }

        $created = 0;
        $base = $campaign->scheduled_at ?? now();

        DB::transaction(function () use ($campaign, $steps, $memberIds, $base, &$created) {
            foreach ($memberIds as $uid) {
                $user = User::find($uid);
                if (! $user) {
                    continue;
                }

                $variant = $this->assignVariant($campaign, $user);

                $cursor = $base;
                foreach ($steps as $step) {
                    if ($step->variant_label && $variant && $step->variant_label !== $variant) {
                        continue;
                    }

                    $cursor = $cursor->copy()->addMinutes((int) $step->delay_minutes);

                    $optedOut = $this->optOut->isOptedOut($user, $step->channel);

                    $recipientStatus = $optedOut
                        ? MarketingCampaignRecipient::STATUS_OPTED_OUT
                        : MarketingCampaignRecipient::STATUS_QUEUED;

                    $idemKey = "campaign:{$campaign->id}:step:{$step->id}:user:{$user->id}";

                    $existing = MarketingCampaignRecipient::query()
                        ->where('idempotency_key', $idemKey)->first();
                    if ($existing) {
                        continue;
                    }

                    MarketingCampaignRecipient::create([
                        'campaign_id' => $campaign->id,
                        'step_id' => $step->id,
                        'user_id' => $user->id,
                        'channel' => $step->channel,
                        'status' => $recipientStatus,
                        'idempotency_key' => $idemKey,
                        'scheduled_for' => $cursor,
                        'variant_label' => $variant,
                    ]);
                    $created++;
                }
            }

            $campaign->forceFill([
                'status' => MarketingCampaign::STATUS_SCHEDULED,
                'scheduled_at' => $campaign->scheduled_at ?? now(),
            ])->save();
        });

        ActivityLogger::log('marketing.campaign_scheduled', $campaign, [
            'recipients_created' => $created,
        ]);

        return $created;
    }

    public function dispatchDueRecipients(int $limit = 200): int
    {
        $sent = 0;
        $rows = MarketingCampaignRecipient::query()
            ->readyToSend()
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get();

        foreach ($rows as $recipient) {
            try {
                $this->dispatchOne($recipient);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('CampaignEngine: dispatch failed', [
                    'recipient_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
                $recipient->forceFill([
                    'status' => MarketingCampaignRecipient::STATUS_FAILED,
                    'failed_at' => now(),
                    'failed_reason' => $e->getMessage(),
                ])->save();
            }
        }
        return $sent;
    }

    public function dispatchOne(MarketingCampaignRecipient $recipient): void
    {
        if ($recipient->status !== MarketingCampaignRecipient::STATUS_QUEUED) {
            return;
        }

        $user = $recipient->user;
        $step = $recipient->step;
        if (! $user || ! $step) {
            $recipient->forceFill([
                'status' => MarketingCampaignRecipient::STATUS_FAILED,
                'failed_at' => now(),
                'failed_reason' => 'user or step missing',
            ])->save();
            return;
        }

        // Re-check opt-out at dispatch time (user may have opted-out after queue)
        if ($this->optOut->isOptedOut($user, $step->channel)) {
            $recipient->forceFill([
                'status' => MarketingCampaignRecipient::STATUS_OPTED_OUT,
            ])->save();
            return;
        }

        $body = $this->renderBody($step, $user, $recipient);

        switch ($step->channel) {
            case MarketingCampaignStep::CHANNEL_EMAIL:
                $this->sendEmail($user, $step->subject ?? $recipient->campaign->name, $body);
                break;
            case MarketingCampaignStep::CHANNEL_SMS:
                $this->sendSms($user, $body);
                break;
            case MarketingCampaignStep::CHANNEL_PUSH:
                $this->sendPush($user, $step->subject, $body);
                break;
            default:
                throw new \InvalidArgumentException("Unknown channel: {$step->channel}");
        }

        $recipient->forceFill([
            'status' => MarketingCampaignRecipient::STATUS_SENT,
            'sent_at' => now(),
        ])->save();

        ActivityLogger::log('marketing.recipient_sent', $recipient, [
            'channel' => $step->channel,
            'campaign_id' => $recipient->campaign_id,
        ]);
    }

    public function pause(MarketingCampaign $campaign): void
    {
        $campaign->forceFill(['status' => MarketingCampaign::STATUS_PAUSED])->save();
    }

    public function resume(MarketingCampaign $campaign): void
    {
        $campaign->forceFill(['status' => MarketingCampaign::STATUS_RUNNING])->save();
    }

    public function cancel(MarketingCampaign $campaign): void
    {
        $campaign->forceFill([
            'status' => MarketingCampaign::STATUS_CANCELLED,
            'ended_at' => now(),
        ])->save();

        // Mark all pending recipients as skipped
        MarketingCampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', MarketingCampaignRecipient::STATUS_QUEUED)
            ->update([
                'status' => MarketingCampaignRecipient::STATUS_SKIPPED,
            ]);
    }

    public function assignVariant(MarketingCampaign $campaign, User $user): ?string
    {
        if (! Config::get('marketing.ab_test_enabled', true)) {
            return null;
        }
        $variants = (array) ($campaign->ab_test_config['variants'] ?? []);
        if (empty($variants)) {
            return null;
        }
        $hash = crc32($campaign->code . ':' . $user->id);
        return (string) $variants[$hash % count($variants)];
    }

    protected function renderBody(MarketingCampaignStep $step, User $user, MarketingCampaignRecipient $recipient): string
    {
        $overrides = (array) $step->content_overrides;
        $template = $overrides['body'] ?? "Hello {name}!";
        return strtr($template, [
            '{name}' => $user->name ?? '',
            '{email}' => $user->email ?? '',
            '{variant}' => (string) ($recipient->variant_label ?? ''),
        ]);
    }

    protected function sendEmail(User $user, string $subject, string $body): void
    {
        // In production : Mail::to($user)->queue(new GenericMarketingMail($subject, $body));
        // For now, log-only in dev/tests so no SMTP needed.
        Log::info('Marketing email send', [
            'to' => $user->email,
            'subject' => $subject,
        ]);
    }

    protected function sendSms(User $user, string $body): void
    {
        if (class_exists(\App\Services\Notifications\SmsService::class) && $user->phone) {
            app(\App\Services\Notifications\SmsService::class)->dispatch(
                toPhone: $user->phone,
                body: $body,
                user: $user,
                category: \App\Models\SmsMessage::CATEGORY_MARKETING,
            );
            return;
        }
        Log::info('Marketing sms send (no SmsService)', [
            'to' => $user->phone,
        ]);
    }

    protected function sendPush(User $user, ?string $title, string $body): void
    {
        if (class_exists(\App\Services\Push\PushService::class)) {
            app(\App\Services\Push\PushService::class)->dispatchToUser(
                user: $user,
                title: $title,
                body: $body,
                category: \App\Models\PushNotification::CATEGORY_MARKETING,
            );
            return;
        }
        Log::info('Marketing push send (no PushService)', ['to' => $user->id]);
    }
}
