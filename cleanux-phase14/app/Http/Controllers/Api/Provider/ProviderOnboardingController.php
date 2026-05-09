<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderOnboardingDocument;
use App\Services\Onboarding\ProviderOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 14 — API onboarding prestataire (mobile/web).
 *
 *   POST   /api/provider/onboarding/start             → crée ProviderProfile vide
 *   GET    /api/provider/onboarding/progress          → état d'avancement
 *   POST   /api/provider/onboarding/profile           → étape 0 (bio, photo)
 *   POST   /api/provider/onboarding/documents         → upload doc (multipart)
 *   POST   /api/provider/onboarding/tax               → étape 2 (tax_id)
 *   POST   /api/provider/onboarding/skills            → étape 4 (skills + zones)
 *
 * NB : Stripe Connect onboarding (étape 5) utilise /provider/onboarding/refresh
 * et /provider/onboarding/done (existaient déjà avec le service Stripe Connect).
 */
class ProviderOnboardingController extends Controller
{
    public function __construct(
        protected ProviderOnboardingService $onboarding,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $profile = $this->onboarding->startOnboarding($request->user());

        return response()->json([
            'ok'              => true,
            'profile_id'      => $profile->id,
            'current_step'    => $profile->onboarding_step,
            'total_steps'     => 7,
        ], 201);
    }

    public function progress(Request $request): JsonResponse
    {
        return response()->json([
            'ok'       => true,
            'progress' => $this->onboarding->getProgress($request->user()),
        ]);
    }

    public function setProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bio'   => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'], // 5 Mo
        ]);

        $profile = $this->onboarding->setProfileBasics(
            $request->user(),
            $data,
            $request->file('photo'),
        );

        return response()->json([
            'ok'           => true,
            'current_step' => $profile->onboarding_step,
            'photo_path'   => $profile->photo_path,
        ]);
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:50'],
            'file'          => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'], // 10 Mo
        ]);

        try {
            $doc = $this->onboarding->uploadDocument(
                $request->user(),
                $data['document_type'],
                $request->file('file'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'        => true,
            'document'  => [
                'id'            => $doc->id,
                'type'          => $doc->document_type,
                'status'        => $doc->status,
                'file_name'     => $doc->file_name,
                'uploaded_at'   => $doc->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function setTax(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tax_id' => ['nullable', 'string', 'max:30'],
        ]);

        $profile = $this->onboarding->setTaxInfo($request->user(), $data['tax_id'] ?? null);

        return response()->json([
            'ok'           => true,
            'current_step' => $profile->onboarding_step,
        ]);
    }

    public function setSkills(Request $request): JsonResponse
    {
        $data = $request->validate([
            'skills'             => ['required', 'array', 'min:1'],
            'skills.*'           => ['string', 'max:100'],
            'service_zone_ids'   => ['nullable', 'array'],
            'service_zone_ids.*' => ['integer', 'exists:service_zones,id'],
        ]);

        $profile = $this->onboarding->setSkills(
            $request->user(),
            $data['skills'],
            $data['service_zone_ids'] ?? [],
        );

        return response()->json([
            'ok'           => true,
            'current_step' => $profile->onboarding_step,
        ]);
    }
}
