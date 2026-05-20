<?php

namespace App\Services\Quality;

use App\Models\ClientSignature;
use App\Models\InspectionItem;
use App\Models\InspectionPhoto;
use App\Models\MissionQualityInspection;
use App\Models\QualityChecklistItem;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * QualityInspectionService — orchestre le cycle de vie d'une inspection.
 *
 *   - start($missionId, $phase, $provider) → MissionQualityInspection draft
 *   - submitItem($inspection, $checklistItem, $value, $comment) → InspectionItem
 *   - attachPhoto($inspection, $file, $itemId?, $photoType, $uploader) → InspectionPhoto
 *   - submit($inspection, $provider) → status=submitted + score recomputed
 *   - validateByClient($inspection, $client, $signatureData, $signerName)
 *   - dispute($inspection, $client, $reason)
 *   - validateByAdmin($inspection, $admin)
 *   - reject($inspection, $admin, $reason)
 */
class QualityInspectionService
{
    public function __construct(
        protected QualityChecklistResolver $resolver,
        protected QualityScoringEngine $scorer,
    ) {}

    public function start(int $missionId, string $phase, ?User $provider = null, ?int $bookingId = null): MissionQualityInspection
    {
        if (! Config::get('quality.enabled', true)) {
            throw ValidationException::withMessages(['module' => 'Quality module disabled.']);
        }
        $this->ensureValidPhase($phase);

        $checklist = $this->resolver->resolveForMission($missionId, $phase);
        if (! $checklist) {
            throw ValidationException::withMessages(['checklist' => "No active checklist for phase {$phase}."]);
        }

        $idempotencyKey = "inspection:{$missionId}:{$phase}:checklist:{$checklist->id}";
        $existing = MissionQualityInspection::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing) {
            return $existing;
        }

        $inspection = MissionQualityInspection::create([
            'mission_id' => $missionId,
            'booking_id' => $bookingId,
            'checklist_id' => $checklist->id,
            'phase' => $phase,
            'status' => MissionQualityInspection::STATUS_IN_PROGRESS,
            'submitted_by_user_id' => $provider?->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        ActivityLogger::log('quality.inspection_started', $inspection, [
            'mission_id' => $missionId,
            'phase' => $phase,
            'checklist_code' => $checklist->code,
        ]);

        return $inspection;
    }

    public function submitItem(
        MissionQualityInspection $inspection,
        QualityChecklistItem $checklistItem,
        array $value,
        ?string $comment = null,
        ?User $recorder = null,
    ): InspectionItem {
        if ($checklistItem->checklist_id !== $inspection->checklist_id) {
            throw ValidationException::withMessages(['checklist_item' => 'Item does not belong to inspection checklist.']);
        }
        if (in_array($inspection->status, [
            MissionQualityInspection::STATUS_VALIDATED_CLIENT,
            MissionQualityInspection::STATUS_VALIDATED_ADMIN,
            MissionQualityInspection::STATUS_REJECTED,
        ], true)) {
            throw ValidationException::withMessages(['status' => "Inspection {$inspection->status} is read-only."]);
        }

        $row = InspectionItem::query()
            ->where('inspection_id', $inspection->id)
            ->where('checklist_item_id', $checklistItem->id)
            ->first();

        if ($row) {
            $row->forceFill([
                'value' => $value,
                'comment' => $comment ?? $row->comment,
                'recorded_by_user_id' => $recorder?->id ?? $row->recorded_by_user_id,
                'recorded_at' => now(),
            ])->save();
        } else {
            $row = InspectionItem::create([
                'inspection_id' => $inspection->id,
                'checklist_item_id' => $checklistItem->id,
                'value' => $value,
                'comment' => $comment,
                'recorded_by_user_id' => $recorder?->id,
                'recorded_at' => now(),
            ]);
        }

        $inspection->forceFill([
            'status' => MissionQualityInspection::STATUS_IN_PROGRESS,
        ])->save();

        return $row->fresh();
    }

    public function attachPhoto(
        MissionQualityInspection $inspection,
        UploadedFile $file,
        ?int $inspectionItemId = null,
        string $photoType = InspectionPhoto::TYPE_DURING,
        ?User $uploader = null,
        ?Request $request = null,
    ): InspectionPhoto {
        $disk = (string) Config::get('quality.photo_storage_disk', 'public');
        $prefix = (string) Config::get('quality.photo_path_prefix', 'quality/photos');
        $maxMb = (int) Config::get('quality.photo_max_size_mb', 8);

        if ($file->getSize() > $maxMb * 1024 * 1024) {
            throw ValidationException::withMessages(['photo' => "Photo dépasse {$maxMb}MB."]);
        }

        $filename = sprintf(
            '%s/%d/%s.%s',
            $prefix,
            $inspection->id,
            Str::lower(Str::random(16)),
            $file->getClientOriginalExtension() ?: 'jpg',
        );

        $stored = Storage::disk($disk)->putFileAs(dirname($filename), $file, basename($filename));

        $photo = InspectionPhoto::create([
            'inspection_id' => $inspection->id,
            'inspection_item_id' => $inspectionItemId,
            'photo_path' => $stored,
            'photo_type' => $photoType,
            'uploaded_by_user_id' => $uploader?->id,
            'uploaded_at' => now(),
            'ip_hash' => $request?->ip() ? hash('sha256', (string) $request->ip()) : null,
        ]);

        if ($inspectionItemId) {
            InspectionItem::query()->where('id', $inspectionItemId)->increment('photos_count');
        }

        return $photo;
    }

    public function submit(MissionQualityInspection $inspection, User $provider): MissionQualityInspection
    {
        if ($inspection->submitted_by_user_id && $inspection->submitted_by_user_id !== $provider->id) {
            throw ValidationException::withMessages(['provider' => 'Only the assigned provider can submit this inspection.']);
        }

        $this->scorer->recompute($inspection);

        $inspection->forceFill([
            'status' => MissionQualityInspection::STATUS_SUBMITTED,
            'submitted_by_user_id' => $provider->id,
            'submitted_at' => now(),
        ])->save();

        ActivityLogger::log('quality.inspection_submitted', $inspection->fresh(), [
            'mission_id' => $inspection->mission_id,
            'score_percent' => $inspection->fresh()->scorePercent(),
            'grade' => $this->scorer->gradeFor($inspection->fresh()),
        ]);

        $fresh = $inspection->fresh();
        \App\Support\Webhooks\BusinessEventEmitter::emit(
            eventCode: 'inspection.completed',
            payload: [
                'inspection_id' => $fresh->id,
                'mission_id' => $fresh->mission_id,
                'phase' => $fresh->phase ?? null,
                'score_percent' => $fresh->scorePercent(),
                'grade' => $this->scorer->gradeFor($fresh),
                'submitted_by' => $provider->id,
                'submitted_at' => $fresh->submitted_at?->toIso8601String(),
            ],
            idempotencyKey: 'inspection.completed:' . $fresh->id,
            sourceType: MissionQualityInspection::class,
            sourceId: (int) $fresh->id,
        );

        return $fresh;
    }

    public function validateByClient(
        MissionQualityInspection $inspection,
        User $client,
        ?string $signatureData = null,
        ?string $signerName = null,
        ?Request $request = null,
    ): MissionQualityInspection {
        if ($inspection->status !== MissionQualityInspection::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Only submitted inspections can be validated by client.']);
        }

        $sigRequired = (bool) Config::get('quality.signature_required_for_client_validation', true);
        if ($sigRequired && empty($signatureData)) {
            throw ValidationException::withMessages(['signature' => 'Signature électronique requise.']);
        }

        return DB::transaction(function () use ($inspection, $client, $signatureData, $signerName, $request) {
            if ($signatureData) {
                ClientSignature::create([
                    'inspection_id' => $inspection->id,
                    'signer_user_id' => $client->id,
                    'signer_name' => $signerName ?: ($client->name ?? $client->email ?? 'unknown'),
                    'signer_email_hash' => $client->email ? hash('sha256', $client->email) : null,
                    'signature_data' => $signatureData,
                    'signed_at' => now(),
                    'ip_hash' => $request?->ip() ? hash('sha256', (string) $request->ip()) : null,
                    'user_agent_short' => $request?->userAgent() ? Str::limit((string) $request->userAgent(), 191, '') : null,
                    'terms_version' => (string) Config::get('quality.signature_terms_version', '2026-05-v1'),
                ]);
            }

            $inspection->forceFill([
                'status' => MissionQualityInspection::STATUS_VALIDATED_CLIENT,
                'validated_by_user_id' => $client->id,
                'validated_at' => now(),
            ])->save();

            ActivityLogger::log('quality.inspection_validated_by_client', $inspection, [
                'mission_id' => $inspection->mission_id,
                'has_signature' => (bool) $signatureData,
            ]);

            return $inspection->fresh();
        });
    }

    public function dispute(MissionQualityInspection $inspection, User $client, string $reason): MissionQualityInspection
    {
        if ($inspection->status !== MissionQualityInspection::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Only submitted inspections can be disputed.']);
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Raison du litige trop courte (10 caractères min).']);
        }

        $inspection->forceFill([
            'status' => MissionQualityInspection::STATUS_DISPUTED,
            'dispute_reason' => mb_substr($reason, 0, 2000),
            'disputed_at' => now(),
            'validated_by_user_id' => $client->id,
        ])->save();

        ActivityLogger::log('quality.inspection_disputed', $inspection, [
            'mission_id' => $inspection->mission_id,
            'client_id' => $client->id,
        ]);

        return $inspection->fresh();
    }

    public function validateByAdmin(MissionQualityInspection $inspection, User $admin): MissionQualityInspection
    {
        if (! in_array($inspection->status, [
            MissionQualityInspection::STATUS_SUBMITTED,
            MissionQualityInspection::STATUS_DISPUTED,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'Only submitted/disputed inspections can be validated by admin.']);
        }

        $inspection->forceFill([
            'status' => MissionQualityInspection::STATUS_VALIDATED_ADMIN,
            'validated_by_user_id' => $admin->id,
            'validated_at' => now(),
        ])->save();

        ActivityLogger::log('quality.inspection_validated_by_admin', $inspection, [
            'admin_id' => $admin->id,
        ]);

        return $inspection->fresh();
    }

    public function reject(MissionQualityInspection $inspection, User $admin, string $reason): MissionQualityInspection
    {
        $inspection->forceFill([
            'status' => MissionQualityInspection::STATUS_REJECTED,
            'dispute_reason' => mb_substr($reason, 0, 2000),
            'validated_by_user_id' => $admin->id,
            'validated_at' => now(),
        ])->save();

        ActivityLogger::log('quality.inspection_rejected', $inspection, [
            'admin_id' => $admin->id,
        ]);

        return $inspection->fresh();
    }

    protected function ensureValidPhase(string $phase): void
    {
        $phases = (array) Config::get('quality.phases', []);
        if (! in_array($phase, $phases, true)) {
            throw ValidationException::withMessages(['phase' => "Phase {$phase} non supportée."]);
        }
    }
}
