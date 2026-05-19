<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RiskEvaluation;
use App\Models\RiskHold;
use App\Services\Risk\RiskScoringEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin endpoints pour Risk v2 :
 *   - GET /api/admin/risk/evaluations — listing
 *   - GET /api/admin/risk/holds — listing pending
 *   - POST /api/admin/risk/holds/{hold}/review — approuve/rejette
 */
class RiskController extends Controller
{
    public function __construct(protected RiskScoringEngine $engine)
    {
    }

    public function evaluations(Request $request): JsonResponse
    {
        $rows = RiskEvaluation::query()
            ->with('user:id,name,email')
            ->when($request->filled('context'), fn ($q) => $q->where('context', $request->string('context')))
            ->when($request->filled('decision'), fn ($q) => $q->where('decision', $request->string('decision')))
            ->orderByDesc('evaluated_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function holds(Request $request): JsonResponse
    {
        $rows = RiskHold::query()
            ->with(['user:id,name,email', 'evaluation:id,context,score,decision,reason'])
            ->when($request->boolean('only_active', true), fn ($q) => $q->where('status', RiskHold::STATUS_ACTIVE))
            ->orderByDesc('created_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function reviewHold(Request $request, RiskHold $hold): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $reviewed = $this->engine->reviewHold($hold, $request->user(), $data['decision'], $data['notes'] ?? null);

        return response()->json([
            'ok' => true,
            'hold' => [
                'id' => $reviewed->id,
                'status' => $reviewed->status,
                'reviewed_at' => $reviewed->reviewed_at,
                'reviewed_by_user_id' => $reviewed->reviewed_by_user_id,
            ],
        ]);
    }
}
