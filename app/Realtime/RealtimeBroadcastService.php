<?php

namespace App\Realtime;

use App\Models\BroadcastEvent;
use App\Realtime\Contracts\TracksBroadcastLedger;
use App\Support\ActivityLogger;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Log;

/**
 * Service centralisé de publication d'events broadcast (Phase Realtime v2).
 *
 *   - Stocke chaque broadcast dans `broadcast_events` pour audit / replay / debug
 *   - Idempotence via clé unique (si l'event implémente TracksBroadcastLedger)
 *   - Wrap autour de event() Laravel, ne casse pas les events déjà existants
 *   - ActivityLogger audit
 *
 * Usage :
 *   app(RealtimeBroadcastService::class)->publish($event);
 *
 * Si $event implémente TracksBroadcastLedger → ledger complet + idempotence.
 * Sinon → broadcast direct sans ledger (best-effort).
 */
class RealtimeBroadcastService
{
    public function publish(ShouldBroadcast|ShouldBroadcastNow $event): ?BroadcastEvent
    {
        if ($event instanceof TracksBroadcastLedger) {
            return $this->publishTracked($event);
        }

        $this->dispatchUntracked($event);
        return null;
    }

    public function replay(BroadcastEvent $row): bool
    {
        try {
            // Replay re-broadcasts the stored payload on the original channel.
            // We don't reconstruct the original event class (its constructor
            // usually wants live Eloquent models we can't serialize round-trip);
            // instead we dispatch a generic ShouldBroadcastNow whose `broadcastAs`
            // matches the original — listeners on the client side react the same.
            $event = new \App\Events\Realtime\GenericReplayedBroadcast(
                channelName: $row->channel,
                broadcastAsName: $row->broadcast_as ?? class_basename($row->event_class),
                payload: (array) $row->payload,
            );

            $this->dispatchUntracked($event);

            $row->increment('attempts');
            $row->forceFill([
                'status' => BroadcastEvent::STATUS_SENT,
                'sent_at' => now(),
                'failed_reason' => null,
            ])->save();

            return true;
        } catch (\Throwable $e) {
            $row->increment('attempts');
            $row->forceFill([
                'status' => BroadcastEvent::STATUS_FAILED,
                'failed_reason' => $e->getMessage(),
                'failed_at' => now(),
            ])->save();
            Log::warning('RealtimeBroadcastService::replay failed', [
                'event_id' => $row->id,
                'class' => $row->event_class,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function publishTracked(ShouldBroadcast|ShouldBroadcastNow $event): BroadcastEvent
    {
        /** @var TracksBroadcastLedger $event */
        $idempotencyKey = $event->broadcastIdempotencyKey();

        if ($idempotencyKey) {
            $existing = BroadcastEvent::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $channelName = $this->extractChannelName($event);

        $row = BroadcastEvent::create([
            'channel' => $channelName,
            'event_class' => $event::class,
            'broadcast_as' => method_exists($event, 'broadcastAs') ? $event->broadcastAs() : null,
            'audience' => $this->detectAudience($event),
            'audience_id' => $this->extractAudienceId($event, $channelName),
            'category' => $event->broadcastCategory(),
            'payload' => $event->broadcastLedgerPayload(),
            'status' => BroadcastEvent::STATUS_QUEUED,
            'attempts' => 0,
            'source_type' => $event->broadcastSourceType(),
            'source_id' => $event->broadcastSourceId(),
            'idempotency_key' => $idempotencyKey,
            'queued_at' => now(),
        ]);

        try {
            $this->dispatchUntracked($event);

            $row->forceFill([
                'status' => BroadcastEvent::STATUS_SENT,
                'attempts' => 1,
                'sent_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::error('RealtimeBroadcastService::publishTracked failed', [
                'event_class' => $event::class,
                'error' => $e->getMessage(),
            ]);
            $row->forceFill([
                'status' => BroadcastEvent::STATUS_FAILED,
                'attempts' => 1,
                'failed_at' => now(),
                'failed_reason' => $e->getMessage(),
            ])->save();
        }

        ActivityLogger::log('realtime.broadcast', $row, [
            'channel' => $channelName,
            'category' => $event->broadcastCategory(),
            'status' => $row->status,
        ]);

        return $row->fresh();
    }

    protected function dispatchUntracked(ShouldBroadcast|ShouldBroadcastNow $event): void
    {
        // Use Laravel's broadcast() helper rather than event() so we go through
        // the broadcasting layer directly (broadcastWhen, queues, etc.).
        broadcast($event);
    }

    protected function extractChannelName(ShouldBroadcast|ShouldBroadcastNow $event): string
    {
        $channels = $event->broadcastOn();
        if (! is_array($channels)) {
            $channels = [$channels];
        }

        foreach ($channels as $channel) {
            if ($channel instanceof Channel) {
                return $channel->name;
            }
            if (is_string($channel)) {
                return $channel;
            }
        }

        return 'unknown';
    }

    protected function detectAudience(ShouldBroadcast|ShouldBroadcastNow $event): string
    {
        $channels = $event->broadcastOn();
        if (! is_array($channels)) {
            $channels = [$channels];
        }

        foreach ($channels as $channel) {
            if ($channel instanceof PresenceChannel) {
                return BroadcastEvent::AUDIENCE_PRESENCE;
            }
        }

        $name = $this->extractChannelName($event);
        if (str_starts_with($name, 'user.') || str_starts_with($name, 'private-user.')) {
            return BroadcastEvent::AUDIENCE_PER_USER;
        }
        if (str_starts_with($name, 'presence-')) {
            return BroadcastEvent::AUDIENCE_PRESENCE;
        }
        if (str_starts_with($name, 'private-')) {
            return BroadcastEvent::AUDIENCE_PER_CHANNEL;
        }

        return BroadcastEvent::AUDIENCE_BROADCAST;
    }

    protected function extractAudienceId(ShouldBroadcast|ShouldBroadcastNow $event, string $channelName): ?int
    {
        // Best-effort: extract trailing int id from channel name "user.42", "mission.5", etc.
        if (preg_match('/\.(\d+)$/', $channelName, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
