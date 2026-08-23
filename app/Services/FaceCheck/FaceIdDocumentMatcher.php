<?php

namespace App\Services\FaceCheck;

use App\Models\ProviderFaceProfile;
use App\Models\ProviderOnboardingDocument;
use App\Services\FaceCheck\Data\FaceDocumentCompareRequest;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Storage;

/** LE VISAGE ENRÔLÉ CONTRE LE PORTRAIT DE LA PIÈCE D'IDENTITÉ. */
class FaceIdDocumentMatcher
{
    /** Les trois pièces qui portent un portrait. */
    private const TYPES_AVEC_PORTRAIT = [
        ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
        ProviderOnboardingDocument::TYPE_PASSPORT,
        ProviderOnboardingDocument::TYPE_RESIDENCE_PERMIT,
    ];

    public function __construct(
        private readonly FaceMatchProviderInterface $matcher,
        private readonly FaceImageStore $store,
        private readonly FaceCheckSettings $settings,
        private readonly FaceCheckIncidentService $incidents,
        private readonly FaceCheckService $service,
    ) {}

    public function match(ProviderFaceProfile $profil): ProviderFaceProfile
    {
        $provider = $profil->user;

        if ($provider === null || ! $profil->isEnrolled()) {
            return $profil;
        }

        $reference = $this->store->get($profil->reference_path);

        if ($reference === null) {
            return $this->conclure($profil, ProviderFaceProfile::MATCH_INCONCLUSIVE, null, 'reference_unreadable', null);
        }

        $document = $this->pieceDIdentite($profil);

        if ($document === null) {
            // Aucune pièce déposée : ce n'est PAS un soupçon.
            return $profil;
        }

        $contenu = $this->contenuDuDocument($document);

        if ($contenu === null) {
            return $this->conclure($profil, ProviderFaceProfile::MATCH_INCONCLUSIVE, null, 'document_unreadable', $document);
        }

        $mime = (string) ($document->mime_type ?: 'application/octet-stream');

        if (! str_starts_with($mime, 'image/')) {
            return $this->conclure($profil, ProviderFaceProfile::MATCH_INCONCLUSIVE, null, 'document_not_an_image', $document);
        }

        $resultat = $this->matcher->compareWithDocument(new FaceDocumentCompareRequest(
            user: $provider,
            referenceContents: $reference,
            documentContents: $contenu,
            documentMimeType: $mime,
            externalApplicantId: $provider->providerProfile?->kyc_external_applicant_id,
        ));

        if (! $resultat->conclusive) {
            return $this->conclure($profil, ProviderFaceProfile::MATCH_INCONCLUSIVE, $resultat->score, $resultat->reason, $document);
        }

        $correspond = ($resultat->score ?? 0.0) >= $this->settings->idMatchThreshold();

        return $this->conclure(
            $profil,
            $correspond ? ProviderFaceProfile::MATCH_OK : ProviderFaceProfile::MATCH_MISMATCH,
            $resultat->score,
            $correspond ? null : ($resultat->reason ?? 'portrait_mismatch'),
            $document,
        );
    }

    private function conclure(
        ProviderFaceProfile $profil,
        string $verdict,
        ?float $score,
        ?string $raison,
        ?ProviderOnboardingDocument $document,
    ): ProviderFaceProfile {
        $profil->forceFill([
            'id_match_status' => $verdict,
            'id_match_score' => $score,
            'id_match_checked_at' => now(),
            'id_match_provider' => $this->matcher->name(),
            'id_document_id' => $document !== null ? $document->id : $profil->id_document_id,
        ])->save();

        try {
            ActivityLogger::log('face_check.id_match', $profil, [
                'verdict' => $verdict,
                'score' => $score,
                'reason' => $raison,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        $provider = $profil->user;

        if ($provider === null) {
            return $profil;
        }

        if ($verdict === ProviderFaceProfile::MATCH_MISMATCH) {
            $this->incidents->noteIdMismatch($provider, $score);
            $this->service->block($profil, ProviderFaceProfile::BLOCK_ID_MISMATCH);
        }

        if ($verdict === ProviderFaceProfile::MATCH_INCONCLUSIVE) {
            $this->incidents->noteIdInconclusive($provider, $raison);
        }

        return $profil->refresh();
    }

    /** La pièce la plus récente parmi celles qui portent un portrait, approuvée de préférence. */
    private function pieceDIdentite(ProviderFaceProfile $profil): ?ProviderOnboardingDocument
    {
        return ProviderOnboardingDocument::query()
            ->where('user_id', $profil->user_id)
            ->whereIn('document_type', self::TYPES_AVEC_PORTRAIT)
            ->whereNotIn('status', [ProviderOnboardingDocument::STATUS_REJECTED])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ProviderOnboardingDocument::STATUS_APPROVED])
            ->latest('id')
            ->first();
    }

    /** Les pièces d'onboarding sont posées EN CLAIR sur le disque privé par `ProviderOnboardingService` — on les lit donc directement, sans passer par `FaceImageStore` qui, lui, ne sait relire que ce qu'il a chiffré. */
    private function contenuDuDocument(ProviderOnboardingDocument $document): ?string
    {
        try {
            $disque = Storage::disk('private');

            if (! filled($document->file_path) || ! $disque->exists($document->file_path)) {
                return null;
            }

            return $disque->get($document->file_path);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
