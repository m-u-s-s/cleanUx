<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Marketing\RecomputeSegmentJob;
use App\Models\MarketingSegment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketingSegmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => MarketingSegment::withCount('members')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'rules' => 'required|array',
        ]);

        if (empty($data['code'])) {
            $data['code'] = 'seg_' . Str::lower(Str::random(12));
        }

        return response()->json(['data' => MarketingSegment::create($data)], 201);
    }

    public function update(Request $request, MarketingSegment $segment): JsonResponse
    {
        $segment->update($request->validate([
            'name'  => 'sometimes|string|max:255',
            'rules' => 'sometimes|array',
        ]));

        return response()->json(['data' => $segment->fresh()]);
    }

    public function destroy(MarketingSegment $segment): JsonResponse
    {
        $segment->delete();

        return response()->json(['ok' => true]);
    }

    public function recompute(MarketingSegment $segment): JsonResponse
    {
        RecomputeSegmentJob::dispatch($segment->id);

        return response()->json(['ok' => true, 'queued' => true]);
    }
}
