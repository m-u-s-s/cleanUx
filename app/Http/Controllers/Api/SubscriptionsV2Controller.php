<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use App\Services\SubscriptionsV2\BillingProcessor;
use App\Services\SubscriptionsV2\SubscriptionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionsV2Controller extends Controller
{
    public function __construct(
        protected SubscriptionEngine $engine,
        protected BillingProcessor $billing,
    ) {}

    public function listPlans(Request $request): JsonResponse
    {
        $rows = SubscriptionPlanV2::query()
            ->active()
            ->when($request->filled('billing_period'), fn ($q) => $q->where('billing_period', $request->string('billing_period')))
            ->orderBy('price_cents')
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function listMySubscriptions(Request $request): JsonResponse
    {
        $rows = SubscriptionV2::query()
            ->where('user_id', $request->user()->id)
            ->with('plan')
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_code' => ['required', 'string', 'max:64'],
            'provider_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'currency' => ['nullable', 'string', 'size:3'],
            'metadata' => ['nullable', 'array'],
        ]);

        $plan = SubscriptionPlanV2::query()->where('code', $data['plan_code'])->first();
        if (! $plan) {
            return response()->json(['ok' => false, 'error' => 'plan_not_found'], 404);
        }

        try {
            $sub = $this->engine->subscribe($request->user(), $plan, [
                'provider_user_id' => $data['provider_user_id'] ?? null,
                'currency' => $data['currency'] ?? null,
                'metadata' => (array) ($data['metadata'] ?? []),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'subscription' => $sub->load('plan')], 201);
    }

    public function pause(Request $request, SubscriptionV2 $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        try {
            $sub = $this->engine->pause($subscription);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'subscription' => $sub]);
    }

    public function resume(Request $request, SubscriptionV2 $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        try {
            $sub = $this->engine->resume($subscription);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'subscription' => $sub]);
    }

    public function cancel(Request $request, SubscriptionV2 $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $immediate = (bool) $request->boolean('immediate');
        $sub = $this->engine->cancel($subscription, $immediate);
        return response()->json(['ok' => true, 'subscription' => $sub]);
    }

    public function changePlan(Request $request, SubscriptionV2 $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'plan_code' => ['required', 'string', 'max:64'],
        ]);
        $plan = SubscriptionPlanV2::query()->where('code', $data['plan_code'])->first();
        if (! $plan) {
            return response()->json(['ok' => false, 'error' => 'plan_not_found'], 404);
        }
        try {
            $sub = $this->engine->changePlan($subscription, $plan);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'subscription' => $sub->load('plan')]);
    }

    public function listMyCycles(Request $request, SubscriptionV2 $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $rows = SubscriptionCycleV2::query()
            ->where('subscription_id', $subscription->id)
            ->orderByDesc('cycle_number')
            ->limit((int) $request->integer('limit', 50))
            ->get();
        return response()->json(['data' => $rows]);
    }

    /* ----- ADMIN ----- */

    public function adminListSubscriptions(Request $request): JsonResponse
    {
        $rows = SubscriptionV2::query()
            ->with(['plan', 'user:id,email,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminListCycles(Request $request): JsonResponse
    {
        $rows = SubscriptionCycleV2::query()
            ->when($request->filled('billing_status'), fn ($q) => $q->where('billing_status', $request->string('billing_status')))
            ->orderByDesc('id')
            ->limit((int) $request->integer('limit', 100))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminRetryBilling(SubscriptionCycleV2 $cycle): JsonResponse
    {
        $row = $this->billing->processCycle($cycle);
        return response()->json(['ok' => true, 'cycle' => $row]);
    }

    public function adminForceCancel(Request $request, SubscriptionV2 $subscription): JsonResponse
    {
        $sub = $this->engine->cancel($subscription, true);
        return response()->json(['ok' => true, 'subscription' => $sub]);
    }
}
