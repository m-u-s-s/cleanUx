<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function me(Request $request, LoyaltyService $service): JsonResponse
    {
        $account = $service->accountFor($request->user());

        $allTiers = LoyaltyTier::query()->active()->ranked()->get();

        $nextTier = $allTiers
            ->filter(fn ($t) => $t->min_period_points > ($account->currentTier?->min_period_points ?? 0))
            ->sortBy('min_period_points')
            ->first();

        return response()->json([
            'lifetime_points' => $account->lifetime_points,
            'period_points' => $account->period_points,
            'tier' => $account->currentTier ? [
                'slug' => $account->currentTier->slug,
                'name' => $account->currentTier->name,
                'icon' => $account->currentTier->icon,
                'color' => $account->currentTier->color,
                'discount_percent' => (float) $account->currentTier->discount_percent,
                'priority_dispatch' => (bool) $account->currentTier->priority_dispatch,
                'vip_support' => (bool) $account->currentTier->vip_support,
                'benefits' => $account->currentTier->benefits,
            ] : null,
            'next_tier' => $nextTier ? [
                'slug' => $nextTier->slug,
                'name' => $nextTier->name,
                'icon' => $nextTier->icon,
                'min_points' => $nextTier->min_period_points,
                'points_to_reach' => max(0, $nextTier->min_period_points - $account->period_points),
            ] : null,
            'multiplier' => (float) $account->tierMultiplier(),
            'tier_started_at' => $account->tier_started_at,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $params = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'type' => ['nullable', 'string'],
        ]);

        $query = LoyaltyTransaction::query()
            ->where('user_id', $request->user()->id)
            ->latest('occurred_at')
            ->latest('id');

        if (! empty($params['type'])) {
            $query->where('type', $params['type']);
        }

        $items = $query->limit($params['limit'] ?? 50)->get();

        return response()->json([
            'data' => $items->map(fn (LoyaltyTransaction $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'direction' => $t->direction,
                'points' => $t->points,
                'reason' => $t->reason,
                'occurred_at' => $t->occurred_at,
            ]),
        ]);
    }
}
