<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Matching\ProviderPerformanceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderPerformanceController extends Controller
{
    public function me(Request $request, ProviderPerformanceCalculator $calculator): JsonResponse
    {
        $user = $request->user();
        $metric = $calculator->latestFor($user->id);

        if (! $metric) {
            $metric = $calculator->calculate($user);
        }

        return response()->json([
            'user_id' => $user->id,
            'period_start' => $metric->period_start,
            'period_end' => $metric->period_end,
            'window_days' => $metric->window_days,
            'offers' => [
                'received' => $metric->offers_received,
                'accepted' => $metric->offers_accepted,
                'declined' => $metric->offers_declined,
                'expired' => $metric->offers_expired,
            ],
            'rates' => [
                'acceptance' => $metric->acceptance_rate !== null ? (float) $metric->acceptance_rate : null,
                'completion' => $metric->completion_rate !== null ? (float) $metric->completion_rate : null,
                'cancellation' => $metric->cancellation_rate !== null ? (float) $metric->cancellation_rate : null,
            ],
            'avg_response_seconds' => $metric->avg_response_seconds,
            'rating' => [
                'avg_window' => $metric->rating_avg_window !== null ? (float) $metric->rating_avg_window : null,
                'count_window' => $metric->rating_count_window,
            ],
            'computed_at' => $metric->computed_at,
        ]);
    }
}
