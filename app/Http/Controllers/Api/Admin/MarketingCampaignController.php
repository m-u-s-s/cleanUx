<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Marketing\RecomputeSegmentJob;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingCampaignStep;
use App\Services\Marketing\CampaignEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketingCampaignController extends Controller
{
    public function __construct(protected CampaignEngine $engine)
    {
    }

    public function index(): JsonResponse
    {
        $items = MarketingCampaign::with('steps')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['data' => $items->items(), 'meta' => ['total' => $items->total()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'type'       => 'required|in:single_blast,drip_sequence,triggered',
            'segment_id' => 'nullable|exists:marketing_segments,id',
            'status'     => 'sometimes|in:draft,active,paused,completed,scheduled,running,cancelled',
        ]);

        if (empty($data['code'])) {
            $data['code'] = 'camp_' . Str::lower(Str::random(12));
        }
        if (empty($data['status'])) {
            $data['status'] = 'draft';
        }

        return response()->json(['data' => MarketingCampaign::create($data)], 201);
    }

    public function update(Request $request, MarketingCampaign $campaign): JsonResponse
    {
        $campaign->update($request->validate([
            'name'   => 'sometimes|string|max:255',
            'status' => 'sometimes|in:draft,active,paused,completed,scheduled,running,cancelled',
        ]));

        return response()->json(['data' => $campaign->fresh()]);
    }

    public function destroy(MarketingCampaign $campaign): JsonResponse
    {
        $campaign->delete();

        return response()->json(['ok' => true]);
    }

    public function schedule(MarketingCampaign $campaign): JsonResponse
    {
        $count = $this->engine->schedule($campaign);

        return response()->json(['ok' => true, 'recipients_created' => $count]);
    }

    public function pause(MarketingCampaign $campaign): JsonResponse
    {
        $this->engine->pause($campaign);

        return response()->json(['ok' => true, 'status' => $campaign->fresh()->status]);
    }

    public function resume(MarketingCampaign $campaign): JsonResponse
    {
        $this->engine->resume($campaign);

        return response()->json(['ok' => true, 'status' => $campaign->fresh()->status]);
    }

    public function cancel(MarketingCampaign $campaign): JsonResponse
    {
        $this->engine->cancel($campaign);

        return response()->json(['ok' => true, 'status' => $campaign->fresh()->status]);
    }

    public function stats(MarketingCampaign $campaign): JsonResponse
    {
        $q = MarketingCampaignRecipient::where('campaign_id', $campaign->id);

        return response()->json([
            'total'      => (clone $q)->count(),
            'sent'       => (clone $q)->where('status', MarketingCampaignRecipient::STATUS_SENT)->count(),
            'failed'     => (clone $q)->where('status', MarketingCampaignRecipient::STATUS_FAILED)->count(),
            'opted_out'  => (clone $q)->where('status', MarketingCampaignRecipient::STATUS_OPTED_OUT)->count(),
            'pending'    => (clone $q)->where('status', MarketingCampaignRecipient::STATUS_QUEUED)->count(),
            'open_rate'  => null,
            'click_rate' => null,
        ]);
    }

    public function storeStep(Request $request, MarketingCampaign $campaign): JsonResponse
    {
        $data = $request->validate([
            'channel'       => 'required|in:email,sms,push',
            'subject'       => 'nullable|string|max:255',
            'body'          => 'required|string',
            'delay_hours'   => 'nullable|integer|min:0',
            'variant_label' => 'nullable|string|max:50',
        ]);

        $position = $campaign->steps()->count() + 1;
        $step = $campaign->steps()->create([
            'channel'       => $data['channel'],
            'subject'       => $data['subject'] ?? null,
            'template_code' => 'inline_' . Str::random(8),
            'delay_minutes' => isset($data['delay_hours']) ? $data['delay_hours'] * 60 : 0,
            'variant_label' => $data['variant_label'] ?? null,
            'position'      => $position,
            'is_active'     => true,
        ]);

        return response()->json(['data' => $step], 201);
    }

    public function updateStep(Request $request, MarketingCampaign $campaign, MarketingCampaignStep $step): JsonResponse
    {
        $step->update($request->validate([
            'subject'       => 'sometimes|string|max:255',
            'body'          => 'sometimes|string',
            'delay_hours'   => 'sometimes|integer|min:0',
            'variant_label' => 'sometimes|nullable|string|max:50',
        ]));

        return response()->json(['data' => $step->fresh()]);
    }

    public function destroyStep(MarketingCampaign $campaign, MarketingCampaignStep $step): JsonResponse
    {
        $step->delete();

        return response()->json(['ok' => true]);
    }

    public function updateAbConfig(Request $request, MarketingCampaign $campaign): JsonResponse
    {
        $data = $request->validate([
            'variants'           => 'required|array|min:2',
            'variants.*.label'   => 'required|string|max:50',
            'variants.*.weight'  => 'required|numeric|min:0|max:1',
        ]);

        $campaign->update(['ab_test_config' => $data['variants']]);

        return response()->json(['ok' => true, 'ab_test_config' => $campaign->fresh()->ab_test_config]);
    }
}
