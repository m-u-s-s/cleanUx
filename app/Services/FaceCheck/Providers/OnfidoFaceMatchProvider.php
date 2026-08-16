<?php

namespace App\Services\FaceCheck\Providers;

use App\Services\FaceCheck\Data\FaceCompareResult;
use App\Services\FaceCheck\Data\FaceDocumentCompareRequest;
use App\Services\FaceCheck\Data\FaceEnrollRequest;
use App\Services\FaceCheck\Data\FaceEnrollResult;
use App\Services\FaceCheck\Data\FaceVerifyRequest;
use App\Services\FaceCheck\Data\FaceVerifyResult;
use App\Services\FaceCheck\FaceMatchProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Adaptateur Onfido pour la comparaison faciale.
 *
 * UN SEUL COMPTE ONFIDO, UN SEUL « APPLICANT » PAR PRESTATAIRE. Le module KYC en crée déjà un à
 * l'onboarding et range son identifiant dans `provider_profiles.kyc_external_applicant_id` : on le
 * réutilise. En créer un second dédierait deux dossiers à la même personne chez le fournisseur, et
 * les rapports des deux ne se compareraient jamais entre eux.
 *
 * CE QUE COMPARE `verify()`, PRÉCISÉMENT. Onfido ne fait pas de selfie-contre-selfie : son rapport
 * `facial_similarity_photo` compare la photo prise à l'instant au PORTRAIT DE LA PIÈCE D'IDENTITÉ
 * déposée chez lui. C'est exactement la question qui nous intéresse — « est-ce bien la personne de
 * la carte ? » — et c'est ainsi que fonctionnent Uber et Bolt avec leurs fournisseurs. Le selfie de
 * référence que nous gardons de notre côté sert à l'œil de l'administrateur et au bouchon ; il
 * n'est pas envoyé à Onfido à chaque contrôle.
 *
 * TOUT EST ASYNCHRONE CHEZ ONFIDO. `verify()` rend donc presque toujours `PENDING`, et c'est
 * `fetchVerification()` qui conclut. La porte reste fermée entre les deux.
 */
class OnfidoFaceMatchProvider implements FaceMatchProviderInterface
{
    /** Les trois variantes de rapport de similarité faciale chez Onfido. */
    private const RAPPORT_FACIAL = 'facial_similarity_photo';

    public function name(): string
    {
        return 'onfido';
    }

    public function enroll(FaceEnrollRequest $request): FaceEnrollResult
    {
        $applicantId = $request->externalApplicantId;

        if (! filled($applicantId)) {
            throw new RuntimeException('Onfido : aucun applicant pour ce prestataire. Le parcours KYC doit avoir été démarré.');
        }

        $reponse = $this->multipart()
            ->attach('file', $request->imageContents, 'reference.jpg', ['Content-Type' => $request->mimeType])
            ->post('/live_photos', ['applicant_id' => $applicantId]);

        if ($reponse->failed()) {
            throw new RuntimeException('Onfido live_photos a échoué : '.$reponse->body());
        }

        return new FaceEnrollResult(
            externalFaceId: (string) $reponse->json('id'),
            externalApplicantId: $applicantId,
            raw: $reponse->json() ?? [],
        );
    }

    public function verify(FaceVerifyRequest $request): FaceVerifyResult
    {
        $applicantId = $request->externalApplicantId;

        if (! filled($applicantId)) {
            return new FaceVerifyResult(
                outcome: FaceVerifyResult::FAILED,
                failureReason: 'onfido_applicant_missing',
            );
        }

        $photo = $this->multipart()
            ->attach('file', $request->probeContents, 'live.jpg', ['Content-Type' => $request->mimeType])
            ->post('/live_photos', ['applicant_id' => $applicantId]);

        if ($photo->failed()) {
            throw new RuntimeException('Onfido live_photos a échoué : '.$photo->body());
        }

        $check = $this->json()->post('/checks', [
            'applicant_id' => $applicantId,
            'report_names' => [self::RAPPORT_FACIAL],
        ]);

        if ($check->failed()) {
            throw new RuntimeException('Onfido checks a échoué : '.$check->body());
        }

        $corps = $check->json() ?? [];

        return $this->verdict($corps, (string) ($corps['id'] ?? ''));
    }

    public function fetchVerification(string $externalCheckId): FaceVerifyResult
    {
        $check = $this->json()->get("/checks/{$externalCheckId}");

        if ($check->failed()) {
            throw new RuntimeException('Onfido fetch check a échoué : '.$check->body());
        }

        return $this->verdict($check->json() ?? [], $externalCheckId);
    }

    public function compareWithDocument(FaceDocumentCompareRequest $request): FaceCompareResult
    {
        $applicantId = $request->externalApplicantId;

        if (! filled($applicantId)) {
            return FaceCompareResult::inconclusive('onfido_applicant_missing');
        }

        /*
         * Un PDF ne se compare pas. Onfido attend une image ; lui envoyer un PDF produit une erreur
         * distante et, faute de mieux, un « mismatch » qui bloquerait un prestataire honnête ayant
         * simplement scanné sa carte au format bureautique. On le dit franchement : non concluant,
         * revue manuelle.
         */
        if (! str_starts_with($request->documentMimeType, 'image/')) {
            return FaceCompareResult::inconclusive('document_not_an_image');
        }

        $photo = $this->multipart()
            ->attach('file', $request->referenceContents, 'reference.jpg', ['Content-Type' => 'image/jpeg'])
            ->post('/live_photos', ['applicant_id' => $applicantId]);

        if ($photo->failed()) {
            return FaceCompareResult::inconclusive('live_photo_upload_failed');
        }

        $check = $this->json()->post('/checks', [
            'applicant_id' => $applicantId,
            'report_names' => [self::RAPPORT_FACIAL],
        ]);

        if ($check->failed()) {
            return FaceCompareResult::inconclusive('check_creation_failed');
        }

        $checkId = (string) ($check->json('id') ?? '');
        $verdict = $this->verdict($check->json() ?? [], $checkId);

        /*
         * L'appariement tourne dans un job : attendre y est légitime. On sonde un nombre borné de
         * fois, puis on rend la main à l'administrateur. Un « non concluant » honnête vaut mieux
         * qu'un job qui tourne dix minutes ou qu'un verdict inventé.
         */
        $tentatives = 0;
        while ($verdict->isPending() && $tentatives < 5 && $checkId !== '') {
            $tentatives++;
            usleep(2_000_000);
            $verdict = $this->fetchVerification($checkId);
        }

        if ($verdict->isPending()) {
            return FaceCompareResult::inconclusive('provider_timeout');
        }

        return new FaceCompareResult(
            conclusive: true,
            score: $verdict->score,
            reason: $verdict->outcome === FaceVerifyResult::PASSED ? null : ($verdict->failureReason ?? 'portrait_mismatch'),
            raw: $verdict->raw,
        );
    }

    /**
     * Traduit une réponse de contrôle Onfido en verdict.
     *
     * @param  array<string, mixed>  $corps
     */
    protected function verdict(array $corps, string $checkId): FaceVerifyResult
    {
        $resultat = $corps['result'] ?? null;
        $statut = $corps['status'] ?? null;

        if ($resultat === null || in_array($statut, ['awaiting_data', 'in_progress', 'awaiting_approval'], true)) {
            return new FaceVerifyResult(
                outcome: FaceVerifyResult::PENDING,
                externalCheckId: $checkId,
                raw: $corps,
            );
        }

        $rapport = $this->rapportFacial($checkId);

        $score = null;
        $vivacite = FaceVerifyResult::LIVENESS_UNKNOWN;
        $motif = null;

        if ($rapport !== null) {
            $brut = $rapport['properties']['score'] ?? null;
            if (is_numeric($brut)) {
                // Onfido rend une similarité entre 0 et 1 ; le module raisonne sur 100.
                $score = (float) $brut <= 1.0 ? (float) $brut * 100 : (float) $brut;
            }

            $vivacite = match ($rapport['breakdown']['image_integrity']['result'] ?? null) {
                'clear' => FaceVerifyResult::LIVENESS_PASS,
                'consider' => FaceVerifyResult::LIVENESS_FAIL,
                default => FaceVerifyResult::LIVENESS_UNKNOWN,
            };

            $motif = filled($rapport['sub_result'] ?? null) ? (string) $rapport['sub_result'] : null;
        }

        $reussi = $resultat === 'clear';

        return new FaceVerifyResult(
            outcome: $reussi ? FaceVerifyResult::PASSED : FaceVerifyResult::FAILED,
            score: $score,
            liveness: $vivacite,
            externalCheckId: $checkId,
            failureReason: $reussi ? null : ($motif ?? 'provider_considered'),
            raw: $corps + ($rapport !== null ? ['facial_report' => $rapport] : []),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function rapportFacial(string $checkId): ?array
    {
        if ($checkId === '') {
            return null;
        }

        try {
            $reponse = $this->json()->get('/reports', ['check_id' => $checkId]);

            if ($reponse->failed()) {
                return null;
            }

            foreach ((array) ($reponse->json('reports') ?? []) as $rapport) {
                if (is_array($rapport) && str_starts_with((string) ($rapport['name'] ?? ''), 'facial_similarity')) {
                    return $rapport;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    protected function json(): PendingRequest
    {
        return $this->base()->acceptJson()->asJson();
    }

    protected function multipart(): PendingRequest
    {
        return $this->base()->acceptJson();
    }

    protected function base(): PendingRequest
    {
        $token = (string) config('face_check.onfido.api_token', '');

        if ($token === '') {
            throw new RuntimeException('Onfido : jeton absent (face_check.onfido.api_token).');
        }

        return Http::withToken($token, 'Token')
            ->baseUrl((string) config('face_check.onfido.base_url'))
            ->timeout((int) config('face_check.onfido.timeout', 30));
    }
}
