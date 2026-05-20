<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessBeneficialOwner;
use App\Models\BusinessDocument;
use App\Models\BusinessEntity;
use App\Models\BusinessSanctionsCheck;
use App\Models\BusinessVerification;
use App\Services\KybV2\BusinessOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class KybV2Controller extends Controller
{
    public function __construct(protected BusinessOnboardingService $onboarding) {}

    /* ---- User self-service ---- */

    public function listMyEntities(Request $request): JsonResponse
    {
        $rows = BusinessEntity::query()
            ->where(function ($q) use ($request) {
                $q->where('owner_user_id', $request->user()->id)
                    ->orWhere('contact_user_id', $request->user()->id);
            })
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function startVerification(Request $request): JsonResponse
    {
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'identifier_type' => ['required', 'string', 'max:24'],
            'identifier_value' => ['required', 'string', 'max:64'],
            'vat_id' => ['nullable', 'string', 'max:32'],
            'legal_form' => ['nullable', 'string', 'max:64'],
            'registered_address' => ['nullable', 'array'],
            'incorporation_date' => ['nullable', 'date'],
            'contact_email' => ['nullable', 'email'],
        ]);
        try {
            $entity = $this->onboarding->startVerification($data, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'entity' => $entity], 201);
    }

    public function showMyEntity(Request $request, BusinessEntity $entity): JsonResponse
    {
        if (! $this->canAccess($request, $entity)) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        return response()->json([
            'data' => $entity->load(['documents', 'verifications', 'sanctionsChecks', 'beneficialOwners']),
        ]);
    }

    public function uploadDocument(Request $request, BusinessEntity $entity): JsonResponse
    {
        if (! $this->canAccess($request, $entity)) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $allowedTypes = (array) config('kyb_v2.document_types', []);
        $allowedMimes = (array) config('kyb_v2.allowed_mime_types', []);
        $maxKb = (int) config('kyb_v2.document_max_size_kb', 10240);
        $data = $request->validate([
            'document_type' => ['required', 'string', 'in:' . implode(',', $allowedTypes)],
            'file' => ['required', 'file', 'max:' . $maxKb],
            'expires_at' => ['nullable', 'date'],
        ]);
        $file = $request->file('file');
        $mime = $file->getMimeType();
        if (! empty($allowedMimes) && ! in_array($mime, $allowedMimes, true)) {
            return response()->json(['ok' => false, 'errors' => ['file' => ["Type MIME non autorisé ({$mime})."]]], 422);
        }
        $disk = (string) config('kyb_v2.document_storage_disk', 'local');
        $prefix = trim((string) config('kyb_v2.document_path_prefix', 'kyb_documents'), '/');
        $name = uniqid('doc_', true) . '_' . preg_replace('/[^a-z0-9_.-]+/i', '_', $file->getClientOriginalName());
        $path = $prefix . '/' . date('Y/m/d') . '/entity-' . $entity->id . '/' . $name;
        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        $doc = BusinessDocument::query()->create([
            'entity_id' => $entity->id,
            'document_type' => $data['document_type'],
            'file_path' => $path,
            'mime_type' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'uploaded_at' => now(),
            'uploaded_by_user_id' => $request->user()->id,
            'status' => BusinessDocument::STATUS_PENDING,
            'expires_at' => $data['expires_at'] ?? null,
        ]);
        return response()->json(['ok' => true, 'document' => $doc], 201);
    }

    /* ---- ADMIN ---- */

    public function adminListEntities(Request $request): JsonResponse
    {
        $rows = BusinessEntity::query()
            ->with(['owner:id,email,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('risk_level'), fn ($q) => $q->where('risk_level', $request->string('risk_level')))
            ->orderByDesc('created_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminRunVerifications(BusinessEntity $entity): JsonResponse
    {
        $row = $this->onboarding->runVerifications($entity);
        return response()->json(['ok' => true, 'entity' => $row]);
    }

    public function adminRunSanctions(BusinessEntity $entity): JsonResponse
    {
        $row = $this->onboarding->runSanctionsScreening($entity);
        return response()->json(['ok' => true, 'entity' => $row]);
    }

    public function adminApprove(Request $request, BusinessEntity $entity): JsonResponse
    {
        $row = $this->onboarding->approve($entity, $request->user());
        return response()->json(['ok' => true, 'entity' => $row]);
    }

    public function adminReject(Request $request, BusinessEntity $entity): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        try {
            $row = $this->onboarding->reject($entity, $data['reason'], $request->user());
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'entity' => $row]);
    }

    public function adminListDocuments(Request $request): JsonResponse
    {
        $rows = BusinessDocument::query()
            ->with('entity:id,code,legal_name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->limit((int) $request->integer('limit', 100))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminReviewDocument(Request $request, BusinessDocument $document): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:approve,reject'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($data['action'] === 'approve') {
            $document->update([
                'status' => BusinessDocument::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $request->user()->id,
                'rejection_reason' => null,
            ]);
        } else {
            if (empty($data['reason']) || mb_strlen($data['reason']) < 10) {
                return response()->json(['ok' => false, 'errors' => ['reason' => ['Raison minimum 10 caractères.']]], 422);
            }
            $document->update([
                'status' => BusinessDocument::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $request->user()->id,
                'rejection_reason' => $data['reason'],
            ]);
        }
        return response()->json(['ok' => true, 'document' => $document->fresh()]);
    }

    public function adminAddBeneficialOwner(Request $request, BusinessEntity $entity): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:191'],
            'date_of_birth' => ['nullable', 'date'],
            'country_of_residence' => ['nullable', 'string', 'size:2'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'ownership_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_director' => ['nullable', 'boolean'],
            'is_pep' => ['nullable', 'boolean'],
        ]);
        $owner = BusinessBeneficialOwner::query()->create(array_merge($data, [
            'entity_id' => $entity->id,
            'aml_status' => BusinessBeneficialOwner::AML_PENDING,
        ]));
        $this->onboarding->refreshRiskAndStatus($entity);
        return response()->json(['ok' => true, 'owner' => $owner], 201);
    }

    public function downloadDocument(Request $request, BusinessDocument $document): Response
    {
        if (! $request->user()) {
            abort(401);
        }
        $entity = $document->entity;
        if (! $entity) {
            abort(404);
        }
        if (! $this->canAccess($request, $entity)) {
            abort(403);
        }
        $disk = (string) config('kyb_v2.document_storage_disk', 'local');
        if (! Storage::disk($disk)->exists($document->file_path)) {
            abort(404);
        }
        return response(
            Storage::disk($disk)->get($document->file_path),
            200,
            [
                'Content-Type' => $document->mime_type,
                'Content-Disposition' => 'attachment; filename="' . basename($document->file_path) . '"',
            ],
        );
    }

    protected function canAccess(Request $request, BusinessEntity $entity): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }
        if ($entity->owner_user_id === $user->id || $entity->contact_user_id === $user->id) {
            return true;
        }
        // admin bypass
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }
        if (($user->role ?? null) === 'admin') {
            return true;
        }
        return false;
    }
}
