<?php

namespace App\Http\Controllers\Push;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\Push\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 8 — Endpoints de gestion des subscriptions push.
 *
 * Routes :
 *   POST   /push/subscribe        Crée ou réactive une subscription
 *   DELETE /push/unsubscribe      Désactive la subscription pour ce device
 *   POST   /push/test             Envoie une notif test (debug)
 *   GET    /push/public-key       Retourne la VAPID public key (pour le JS)
 */
class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'   => ['required', 'string', 'url', 'max:2000'],
            'keys'       => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth'   => ['required', 'string', 'max:255'],
            'platform'   => ['nullable', 'string', 'max:50'],
            'browser'    => ['nullable', 'string', 'max:50'],
        ]);

        $hash = PushSubscription::hashEndpoint($data['endpoint']);

        $subscription = PushSubscription::updateOrCreate(
            [
                'user_id'        => $request->user()->id,
                'endpoint_hash'  => $hash,
            ],
            [
                'endpoint'      => $data['endpoint'],
                'p256dh'        => $data['keys']['p256dh'],
                'auth'          => $data['keys']['auth'],
                'user_agent'    => substr((string) $request->userAgent(), 0, 500),
                'platform'      => $data['platform'] ?? null,
                'browser'       => $data['browser'] ?? null,
                'is_active'     => true,
                'failure_count' => 0,
            ]
        );

        return response()->json([
            'ok' => true,
            'subscription_id' => $subscription->id,
        ]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');
        if (empty($endpoint)) {
            return response()->json(['ok' => false, 'error' => 'endpoint required'], 422);
        }

        $hash = PushSubscription::hashEndpoint($endpoint);

        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', $hash)
            ->update(['is_active' => false]);

        return response()->json(['ok' => true]);
    }

    public function publicKey(): JsonResponse
    {
        return response()->json([
            'public_key' => config('services.webpush.public_key', ''),
        ]);
    }

    public function test(Request $request, WebPushSender $sender): JsonResponse
    {
        $result = $sender->sendToUser($request->user(), [
            'title' => '🧪 Notification test',
            'body'  => 'Si tu vois ce message, les notifications push fonctionnent !',
            'url'   => '/',
            'tag'   => 'test-notification',
            'requireInteraction' => false,
        ]);

        return response()->json([
            'ok'     => true,
            'result' => $result,
        ]);
    }
}
