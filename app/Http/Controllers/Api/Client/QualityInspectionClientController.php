<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\MissionQualityInspection;
use App\Services\Quality\QualityInspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QualityInspectionClientController extends Controller
{
    public function __construct(protected QualityInspectionService $svc)
    {
    }

    public function show(Request $request, MissionQualityInspection $inspection): JsonResponse
    {
        $inspection->load(['checklist.items', 'items', 'photos']);
        return response()->json(['data' => $inspection]);
    }

    public function validate_(Request $request, MissionQualityInspection $inspection): JsonResponse
    {
        $data = $request->validate([
            'signature_data' => ['nullable', 'string', 'max:65536'],
            'signer_name' => ['nullable', 'string', 'max:191'],
            'terms_accepted' => ['required', 'boolean', 'accepted'],
        ]);

        try {
            $row = $this->svc->validateByClient(
                inspection: $inspection,
                client: $request->user(),
                signatureData: $data['signature_data'] ?? null,
                signerName: $data['signer_name'] ?? null,
                request: $request,
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'inspection' => $row]);
    }

    public function dispute(Request $request, MissionQualityInspection $inspection): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        try {
            $row = $this->svc->dispute($inspection, $request->user(), $data['reason']);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'inspection' => $row]);
    }
}
