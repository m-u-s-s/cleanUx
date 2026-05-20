<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Services\Loyalty\LoyaltyRedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoyaltyRedemptionController extends Controller
{
    public function catalogue(Request $request): JsonResponse
    {
        $params = $request->validate([
            'type' => ['nullable', 'string', 'in:discount_code,service_credit,physical_item,partner_voucher,charity_donation'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = LoyaltyReward::query()->active()->inStock()->orderBy('points_cost');
        if (! empty($params['type'])) {
            $query->where('reward_type', $params['type']);
        }

        $items = $query->limit($params['limit'] ?? 50)->get();

        return response()->json([
            'data' => $items->map(fn (LoyaltyReward $r) => [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'description' => $r->description,
                'reward_type' => $r->reward_type,
                'category' => $r->category,
                'points_cost' => (int) $r->points_cost,
                'value_cents' => (int) $r->value_cents,
                'currency' => $r->currency,
                'image_url' => $r->image_url,
                'min_tier_level' => (int) $r->min_tier_level,
                'stock_remaining' => $r->stock_remaining,
                'partner_name' => $r->partner_name,
            ]),
        ]);
    }

    public function redeem(Request $request, LoyaltyRedemptionService $service): JsonResponse
    {
        $params = $request->validate([
            'reward_id' => ['required', 'integer', 'exists:loyalty_rewards,id'],
            'shipping_address' => ['nullable', 'array'],
        ]);

        try {
            $reward = LoyaltyReward::findOrFail($params['reward_id']);
            $redemption = $service->redeem($request->user(), $reward, [
                'shipping_address' => $params['shipping_address'] ?? null,
            ]);

            return response()->json([
                'data' => [
                    'id' => $redemption->id,
                    'code' => $redemption->code,
                    'status' => $redemption->status,
                    'voucher_code' => $redemption->voucher_code,
                    'delivery_method' => $redemption->delivery_method,
                    'points_spent' => (int) $redemption->points_spent,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'validation_failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function mine(Request $request): JsonResponse
    {
        $params = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,confirmed,delivered,cancelled,refunded'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = LoyaltyRedemption::query()
            ->where('user_id', $request->user()->id)
            ->with('reward:id,name,reward_type')
            ->orderByDesc('created_at');
        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $items = $query->limit($params['limit'] ?? 50)->get();

        return response()->json([
            'data' => $items->map(fn (LoyaltyRedemption $d) => [
                'id' => $d->id,
                'code' => $d->code,
                'reward' => $d->reward ? [
                    'id' => $d->reward->id,
                    'name' => $d->reward->name,
                    'reward_type' => $d->reward->reward_type,
                ] : null,
                'status' => $d->status,
                'voucher_code' => $d->voucher_code,
                'delivery_method' => $d->delivery_method,
                'points_spent' => (int) $d->points_spent,
                'created_at' => $d->created_at,
                'delivered_at' => $d->delivered_at,
                'cancelled_at' => $d->cancelled_at,
            ]),
        ]);
    }
}
