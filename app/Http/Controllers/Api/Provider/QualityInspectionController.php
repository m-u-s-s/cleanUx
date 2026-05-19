<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\MissionQualityInspection;
use App\Models\QualityChecklistItem;
use App\Services\Quality\QualityInspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QualityInspectionController extends Controller
{
    public function __construct(protected QualityInspectionService $svc)
    {
    }

    public function index(Request $request, int $mission): JsonResponse
    {
        $rows = MissionQualityInspection::query()
            ->forMission($mission)
            ->with('checklist:id,code,name,phase')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function start(Request $request, int $mission): JsonResponse
    {
        $data = $request->validate([
            'phase' => ['required', 'string', 'max:16'],
            'booking_id' => ['nullable', 'integer'],
        ]);

        try {
            $inspection = $this->svc->start(
                missionId: $mission,
                phase: $data['phase'],
                provider: $request->user(),
                bookingId: $data['booking_id'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        $inspection->load(['checklist.items']);

        return response()->json(['ok' => true, 'inspection' => $inspection], 201);
    }

    public function show(Request $request, MissionQualityInspection $inspection): JsonResponse
    {
        $inspection->load(['checklist.items', 'items', 'photos', 'signatures']);
        return response()->json(['data' => $inspection]);
    }

    public function submitItem(Request $request, MissionQualityInspection $inspection, QualityChecklistItem $checklistItem): JsonResponse
    {
        $data = $request->validate([
            'value' => ['required', 'array'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $item = $this->svc->submitItem(
                inspection: $inspection,
                checklistItem: $checklistItem,
                value: $data['value'],
                comment: $data['comment'] ?? null,
                recorder: $request->user(),
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'item' => $item]);
    }

    public function uploadPhoto(Request $request, MissionQualityInspection $inspection): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic', 'max:16384'],
            'photo_type' => ['required', 'in:before,during,after,defect,signature_proof'],
            'inspection_item_id' => ['nullable', 'integer'],
        ]);

        try {
            $photo = $this->svc->attachPhoto(
                inspection: $inspection,
                file: $request->file('photo'),
                inspectionItemId: $data['inspection_item_id'] ?? null,
                photoType: $data['photo_type'],
                uploader: $request->user(),
                request: $request,
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'photo' => $photo], 201);
    }

    public function submit(Request $request, MissionQualityInspection $inspection): JsonResponse
    {
        try {
            $row = $this->svc->submit($inspection, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'ok' => true,
            'inspection' => $row,
            'score_percent' => $row->scorePercent(),
        ]);
    }
}
