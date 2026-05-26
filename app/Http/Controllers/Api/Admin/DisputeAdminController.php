<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComplaintCase;
use App\Services\Disputes\DisputeResolutionService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisputeAdminController extends Controller
{
    public function __construct(protected DisputeResolutionService $resolutionService)
    {
    }

    public function resolve(Request $request, ComplaintCase $dispute): JsonResponse
    {
        $data = $request->validate([
            'resolution_type' => 'required|in:refund_full,refund_partial,credit,promo_code,replacement_booking,provider_warning,provider_sanction,no_action,dismissed,other',
            'amount'          => 'nullable|numeric|min:0',
            'explanation'     => 'nullable|string|max:2000',
        ]);

        $resolution = $this->resolutionService->apply($dispute, $request->user(), $data);

        return response()->json(['ok' => true, 'resolution' => $resolution]);
    }

    public function escalate(Request $request, ComplaintCase $dispute): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $dispute->update([
            'status'           => ComplaintCase::STATUS_ESCALATED,
            'last_activity_at' => now(),
        ]);

        ActivityLogger::log('dispute.escalated', $dispute, [
            'admin_user_id' => $request->user()->id,
            'reason'        => $data['reason'],
        ]);

        return response()->json(['ok' => true, 'status' => $dispute->fresh()->status]);
    }
}
