<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\InsuranceException;
use App\Http\Controllers\Controller;
use App\Models\InsuranceClaim;
use App\Services\Insurance\InsuranceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin — Insurance Claims
 *
 * @authenticated
 */
class InsuranceAdminController extends Controller
{
    public function __construct(protected InsuranceService $service) {}

    public function claims(): JsonResponse
    {
        $claims = InsuranceClaim::with(['insurance.plan', 'insurance.user'])
            ->orderByDesc('filed_at')
            ->paginate(20);

        return response()->json(['data' => $claims->items(), 'meta' => ['total' => $claims->total()]]);
    }

    public function updateClaimStatus(Request $request, InsuranceClaim $claim): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|string',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $updated = $this->service->updateClaimStatus($claim, $data['status'], $data['notes'] ?? null);
        } catch (\InvalidArgumentException $e) {
            throw InsuranceException::invalidStatusTransition($claim->status, $data['status']);
        }

        return response()->json(['data' => $updated]);
    }
}
