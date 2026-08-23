<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/** Phase 8 — Envoi de notifications Web Push. Wrapper autour de minishlink/web-push. */
class WebPushSender
{
    /**
     * @return array{sent:int, failed:int, deactivated:int}
     */
    public function sendToUser(User $user, array $payload): array
    {
        $subscriptions = PushSubscription::query()
            ->forUser($user->id)
            ->active()
            ->get();

        if ($subscriptions->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'deactivated' => 0];
        }

        return $this->sendToSubscriptions($subscriptions, $payload);
    }

    /** Envoi à plusieurs users. */
    public function sendToUsers(iterable $users, array $payload): array
    {
        $userIds = collect($users)->map(fn ($u) => is_int($u) ? $u : $u->id)->all();

        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $userIds)
            ->active()
            ->get();

        return $this->sendToSubscriptions($subscriptions, $payload);
    }

    protected function sendToSubscriptions($subscriptions, array $payload): array
    {
        if (! class_exists(WebPush::class)) {
            Log::warning('WebPushSender: minishlink/web-push not installed. Run: composer require minishlink/web-push');

            return ['sent' => 0, 'failed' => 0, 'deactivated' => 0];
        }

        $vapid = $this->vapidConfig();
        if (! $vapid) {
            Log::warning('WebPushSender: VAPID keys not configured. Set VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY in .env');

            return ['sent' => 0, 'failed' => 0, 'deactivated' => 0];
        }

        $webPush = new WebPush(['VAPID' => $vapid]);
        $payloadJson = json_encode($this->normalizePayload($payload), JSON_UNESCAPED_UNICODE);

        // Map endpoint_hash → modèle pour traiter les rapports après flush()
        $subById = [];

        foreach ($subscriptions as $sub) {
            $webPushSub = Subscription::create($sub->toWebPushArray());
            $webPush->queueNotification($webPushSub, $payloadJson);
            $subById[$sub->endpoint_hash] = $sub;
        }

        $sent = 0;
        $failed = 0;
        $deactivated = 0;

        foreach ($webPush->flush() as $report) {
            $endpoint = (string) $report->getRequest()->getUri();
            $hash = PushSubscription::hashEndpoint($endpoint);
            $sub = $subById[$hash] ?? null;

            if ($report->isSuccess()) {
                $sent++;
                $sub?->recordSuccess();
            } else {
                $failed++;

                $statusCode = $report->getResponse()?->getStatusCode();
                if (in_array($statusCode, [404, 410], true)) {
                    // Endpoint mort (user a désinstallé/désactivé)
                    $sub?->update(['is_active' => false]);
                    $deactivated++;
                } else {
                    $sub?->recordFailure();
                }

                Log::info('WebPush failed', [
                    'endpoint' => substr($endpoint, 0, 80),
                    'status' => $statusCode,
                    'reason' => $report->getReason(),
                ]);
            }
        }

        return compact('sent', 'failed', 'deactivated');
    }

    protected function vapidConfig(): ?array
    {
        $public = config('services.webpush.public_key');
        $private = config('services.webpush.private_key');
        $subject = config('services.webpush.subject', 'mailto:contact@brio.local');

        if (empty($public) || empty($private)) {
            return null;
        }

        return [
            'subject' => $subject,
            'publicKey' => $public,
            'privateKey' => $private,
        ];
    }

    protected function normalizePayload(array $payload): array
    {
        return array_merge([
            'title' => 'Brio',
            'body' => '',
            'icon' => '/icons/icon-192.png',
            'badge' => '/icons/badge-72.png',
            'url' => '/',
            'tag' => null,
            'requireInteraction' => false,
        ], $payload);
    }
}
