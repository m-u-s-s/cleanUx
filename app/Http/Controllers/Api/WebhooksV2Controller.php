<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;
use App\Services\WebhooksV2\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WebhooksV2Controller extends Controller
{
    public function __construct(protected WebhookDispatcher $dispatcher) {}

    public function adminListEndpoints(Request $request): JsonResponse
    {
        $rows = WebhookEndpoint::query()
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', (bool) $request->boolean('active')))
            ->when($request->filled('owner_user_id'), fn ($q) => $q->where('owner_user_id', $request->integer('owner_user_id')))
            ->orderByDesc('created_at')
            ->limit((int) $request->integer('limit', 50))
            ->get()
            ->makeVisible(['secret']);

        return response()->json(['data' => $rows]);
    }

    public function adminCreateEndpoint(Request $request): JsonResponse
    {
        $allowed = (array) config('webhooks_v2.allowed_events', []);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'url' => ['required', 'url', 'max:500'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'headers' => ['nullable', 'array'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:120'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:20'],
            'event_codes' => ['required', 'array', 'min:1'],
            'event_codes.*' => ['string', Rule::in($allowed)],
        ]);

        $endpoint = WebhookEndpoint::query()->create([
            'code' => WebhookEndpoint::generateCode(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'url' => $data['url'],
            'secret' => WebhookEndpoint::generateSecret(),
            'headers' => $data['headers'] ?? null,
            'timeout_seconds' => $data['timeout_seconds'] ?? (int) config('webhooks_v2.timeout_seconds', 15),
            'max_attempts' => $data['max_attempts'] ?? (int) config('webhooks_v2.max_attempts', 6),
            'is_active' => true,
        ]);

        foreach (array_unique($data['event_codes']) as $code) {
            WebhookSubscription::query()->create([
                'endpoint_id' => $endpoint->id,
                'event_code' => $code,
                'is_active' => true,
            ]);
        }

        return response()->json([
            'ok' => true,
            'endpoint' => $endpoint->fresh('subscriptions')->makeVisible(['secret']),
        ], 201);
    }

    public function adminUpdateEndpoint(Request $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url', 'max:500'],
            'headers' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'is_suspended' => ['nullable', 'boolean'],
            'suspension_reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($request->boolean('reset_consecutive_failures')) {
            $data['consecutive_failures'] = 0;
        }

        $endpoint->update(array_filter($data, fn ($v) => $v !== null));
        return response()->json(['ok' => true, 'endpoint' => $endpoint->fresh()]);
    }

    public function adminRotateSecret(WebhookEndpoint $endpoint): JsonResponse
    {
        $endpoint->update(['secret' => WebhookEndpoint::generateSecret()]);
        return response()->json([
            'ok' => true,
            'endpoint' => $endpoint->fresh()->makeVisible(['secret']),
        ]);
    }

    public function adminDeleteEndpoint(WebhookEndpoint $endpoint): JsonResponse
    {
        $endpoint->delete();
        return response()->json(['ok' => true]);
    }

    public function adminTestEndpoint(Request $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $event = $this->dispatcher->emit(
            eventCode: 'test.ping',
            payload: [
                'message' => 'CleanUx webhook test ping',
                'endpoint_code' => $endpoint->code,
                'fired_at' => now()->toIso8601String(),
            ],
            idempotencyKey: null,
        );
        // Force-create a delivery even if no subscription matched (admin test)
        if ($event) {
            $existing = WebhookDelivery::query()
                ->where('event_id', $event->id)
                ->where('endpoint_id', $endpoint->id)
                ->exists();
            if (! $existing) {
                $delivery = WebhookDelivery::query()->create([
                    'event_id' => $event->id,
                    'endpoint_id' => $endpoint->id,
                    'status' => WebhookDelivery::STATUS_PENDING,
                    'attempt' => 0,
                    'max_attempts' => $endpoint->max_attempts,
                ]);
                \App\Jobs\WebhooksV2\DeliverWebhookJob::dispatch($delivery->id)
                    ->onQueue((string) config('webhooks_v2.queue', 'webhooks'));
            }
        }
        return response()->json(['ok' => true, 'event' => $event]);
    }

    public function adminListEvents(Request $request): JsonResponse
    {
        $rows = WebhookEvent::query()
            ->when($request->filled('event_code'), fn ($q) => $q->where('event_code', $request->string('event_code')))
            ->orderByDesc('id')
            ->limit((int) $request->integer('limit', 50))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminListDeliveries(Request $request): JsonResponse
    {
        $rows = WebhookDelivery::query()
            ->with(['event:id,event_id,event_code,occurred_at', 'endpoint:id,code,name,url'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('endpoint_id'), fn ($q) => $q->where('endpoint_id', $request->integer('endpoint_id')))
            ->orderByDesc('id')
            ->limit((int) $request->integer('limit', 50))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminReplayDelivery(WebhookDelivery $delivery): JsonResponse
    {
        $replayed = $this->dispatcher->replay($delivery);
        return response()->json(['ok' => true, 'delivery' => $replayed]);
    }
}
