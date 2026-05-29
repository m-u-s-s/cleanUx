<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderBadge;
use App\Models\ProviderBadgeAward;
use App\Services\Badges\ProviderBadgeEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Provider — Badges
 *
 * @authenticated
 */
class BadgesController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        $awarded = ProviderBadgeAward::query()
            ->where('provider_user_id', $user->id)
            ->with('badge:id,code,name,description,icon,tier,criterion_type,threshold')
            ->orderByDesc('awarded_at')
            ->get();

        $earnedBadgeIds = $awarded->pluck('badge_id')->toArray();

        $available = ProviderBadge::query()
            ->where('is_active', true)
            ->whereNotIn('id', $earnedBadgeIds)
            ->orderBy('criterion_type')
            ->orderBy('threshold')
            ->get(['id', 'code', 'name', 'description', 'icon', 'tier', 'criterion_type', 'threshold']);

        return response()->json([
            'data' => [
                'awarded' => $awarded->map(fn ($a) => [
                    'badge' => $a->badge,
                    'value_at_award' => (int) $a->value_at_award,
                    'awarded_at' => $a->awarded_at,
                ]),
                'available' => $available,
                'total_earned' => $awarded->count(),
            ],
        ]);
    }

    public function evaluate(Request $request, ProviderBadgeEngine $engine): JsonResponse
    {
        $awarded = $engine->evaluate($request->user());

        return response()->json([
            'data' => [
                'newly_awarded' => count($awarded),
                'awards' => collect($awarded)->map(fn ($a) => [
                    'badge_code' => $a->badge?->code,
                    'value' => (int) $a->value_at_award,
                ]),
            ],
        ]);
    }
}
