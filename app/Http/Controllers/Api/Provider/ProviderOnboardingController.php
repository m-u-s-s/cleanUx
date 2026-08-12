<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderOnboardingDocument;
use App\Models\ServiceZone;
use App\Services\Onboarding\ProviderDocumentRequirements;
use App\Services\Onboarding\ProviderOnboardingService;
use App\Support\Validation\ImagesTeleversees;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Provider — Onboarding (Legacy)
 *
 * @authenticated
 *
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
        protected ProviderDocumentRequirements $requirements,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $profile = $this->onboarding->startOnboarding($request->user());

        return response()->json([
            'ok' => true,
            'profile_id' => $profile->id,
            'current_step' => $profile->onboarding_step,
            'total_steps' => 7,
        ], 201);
    }

    public function progress(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'progress' => $this->onboarding->getProgress($request->user()),
        ]);
    }

    /**
     * La photo part sur le disque `public` (voir `ProviderOnboardingService::setProfileBasics`),
     * donc dans un dossier servi tel quel sur le domaine de l'application. Elle est ensuite regardée
     * par un administrateur qui instruit le dossier : ce que l'on accepte ici s'exécutera dans SA
     * session. Un SVG est un document XML, il porte volontiers un `<script>`.
     *
     * Le wizard web `ProviderOnboardingWizard::saveStep0()` écrit la MÊME photo sur le MÊME disque.
     * Les deux lisent donc la même liste, {@see ImagesTeleversees} : durcir un seul des deux ne
     * ferme rien, l'attaquant prend l'autre porte.
     */
    public function setProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:2000'],
            // 5 Mo, facultative : l'étape sert aussi à saisir un nom seul.
            'photo' => ImagesTeleversees::regles(tailleMaxKo: 5120, obligatoire: false),
        ]);

        $profile = $this->onboarding->setProfileBasics(
            $request->user(),
            $data,
            $request->file('photo'),
        );

        return response()->json([
            'ok' => true,
            'current_step' => $profile->onboarding_step,
            'photo_path' => $profile->photo_path,
        ]);
    }

    /**
     * Justificatifs attendus de ce prestataire, et où en est chacun.
     *
     * Rien ne permettait de lire ses propres documents : l'application ne pouvait donc afficher
     * ni « en cours de vérification », ni « refusé » avec son motif — la mécanique même qui
     * permet de corriger un dossier sans attendre un email. Chaque exigence porte ici son
     * document courant, s'il existe.
     */
    public function documents(Request $request): JsonResponse
    {
        $user = $request->user();

        $documents = ProviderOnboardingDocument::query()
            ->forUser($user->id)
            ->latest('id')
            ->get();

        // Le plus récent par type : `uploadDocument` archive les précédents en `rejected`
        // (« Remplacé par une nouvelle version »), qui ne doivent pas s'afficher comme des refus.
        $latestByType = $documents->unique('document_type');

        $requirements = collect($this->requirements->for($user))
            ->map(function (array $requirement) use ($latestByType): array {
                $document = $latestByType->first(
                    fn (ProviderOnboardingDocument $doc): bool => in_array($doc->document_type, $requirement['accepts'], true),
                );

                return [
                    ...$requirement,
                    'document' => $document ? $this->serializeDocument($document) : null,
                ];
            })
            ->all();

        return response()->json([
            'ok' => true,
            'requirements' => $requirements,
            'documents' => $latestByType->map(fn ($doc) => $this->serializeDocument($doc))->values(),
        ]);
    }

    /**
     * Zones où le prestataire peut intervenir.
     *
     * `POST /provider/onboarding/skills` accepte `service_zone_ids` depuis toujours, mais aucune
     * route ne permettait de connaître les zones existantes : aucun écran ne pouvait donc en
     * proposer, et le champ restait vide. Or sans zone déclarée, le matching géographique n'a
     * rien sur quoi travailler.
     *
     * Seules les zones ouvertes à la réservation et visibles sont listées : proposer une zone
     * inactive reviendrait à laisser un prestataire s'y positionner sans jamais y recevoir de
     * mission.
     */
    public function serviceZones(): JsonResponse
    {
        $zones = ServiceZone::query()
            ->where('is_bookable', true)
            ->where('is_visible', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'coverage_type']);

        return response()->json(['ok' => true, 'zones' => $zones]);
    }

    /** @return array<string, mixed> */
    private function serializeDocument(ProviderOnboardingDocument $document): array
    {
        return [
            'id' => $document->id,
            'type' => $document->document_type,
            'status' => $document->status,
            'file_name' => $document->file_name,
            // Le motif est ce qui rend un refus actionnable : sans lui, le prestataire redépose
            // la même pièce et se fait refuser une seconde fois.
            'rejection_reason' => $document->rejection_reason,
            'uploaded_at' => $document->created_at?->toIso8601String(),
            'reviewed_at' => $document->reviewed_at?->toIso8601String(),
        ];
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:50'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'], // 10 Mo
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
            'ok' => true,
            'document' => [
                'id' => $doc->id,
                'type' => $doc->document_type,
                'status' => $doc->status,
                'file_name' => $doc->file_name,
                'uploaded_at' => $doc->created_at->toIso8601String(),
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
            'ok' => true,
            'current_step' => $profile->onboarding_step,
        ]);
    }

    public function setSkills(Request $request): JsonResponse
    {
        $data = $request->validate([
            'skills' => ['required', 'array', 'min:1'],
            'skills.*' => ['string', 'max:100'],
            'service_zone_ids' => ['nullable', 'array'],
            'service_zone_ids.*' => ['integer', 'exists:service_zones,id'],
        ]);

        $profile = $this->onboarding->setSkills(
            $request->user(),
            $data['skills'],
            $data['service_zone_ids'] ?? [],
        );

        return response()->json([
            'ok' => true,
            'current_step' => $profile->onboarding_step,
        ]);
    }

    // Admin-only (route: auth + role:admin) — reviewers download any provider's onboarding doc.
    public function downloadDocument(ProviderOnboardingDocument $document): BinaryFileResponse
    {
        return response()->file(
            storage_path('app/private/'.$document->file_path)
        );
    }
}
