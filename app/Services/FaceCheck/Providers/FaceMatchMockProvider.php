<?php

namespace App\Services\FaceCheck\Providers;

use App\Services\FaceCheck\Data\FaceCompareResult;
use App\Services\FaceCheck\Data\FaceDocumentCompareRequest;
use App\Services\FaceCheck\Data\FaceEnrollRequest;
use App\Services\FaceCheck\Data\FaceEnrollResult;
use App\Services\FaceCheck\Data\FaceVerifyRequest;
use App\Services\FaceCheck\Data\FaceVerifyResult;
use App\Services\FaceCheck\FaceMatchProviderInterface;

/**
 * LE BOUCHON DÉTERMINISTE — la plateforme entière tourne sans clé, sans facture, sans réseau.
 *
 * Il ne tire rien au sort. Deux images portant la même identité donnent toujours le même score ;
 * c'est ce qui rend les tests reproductibles, là où un faux aléatoire produit une suite qui passe
 * quatre fois sur cinq et qu'on finit par relancer sans lire.
 *
 * L'identité se déclare dans le contenu de l'image, par un marqueur textuel :
 *
 *   "#face:alice"                → c'est Alice
 *   "#face:bob"                  → c'est Bob (donc pas Alice)
 *   "#face:alice#liveness:fail"  → c'est bien Alice, mais sur une photo d'une photo
 *   "#face:alice#pending"        → le fournisseur n'a pas encore conclu
 *   "#unreadable"                → image inexploitable (appariement non concluant)
 *
 * Sans marqueur, l'identité est l'empreinte du contenu : deux fichiers identiques sont la même
 * personne, deux fichiers différents ne le sont pas. C'est le comportement attendu d'un vrai
 * moteur ramené à sa plus simple expression honnête.
 */
class FaceMatchMockProvider implements FaceMatchProviderInterface
{
    public const MARQUEUR_IDENTITE = '#face:';

    public const MARQUEUR_VIVACITE_RATEE = '#liveness:fail';

    public const MARQUEUR_DIFFERE = '#pending';

    public const MARQUEUR_ILLISIBLE = '#unreadable';

    private const SCORE_MEME_PERSONNE = 96.0;

    private const SCORE_AUTRE_PERSONNE = 11.0;

    public function name(): string
    {
        return 'mock';
    }

    public function enroll(FaceEnrollRequest $request): FaceEnrollResult
    {
        return new FaceEnrollResult(
            externalFaceId: 'mock-face-'.$this->identite($request->imageContents),
            externalApplicantId: $request->externalApplicantId,
            raw: ['provider' => 'mock', 'bytes' => strlen($request->imageContents)],
        );
    }

    public function verify(FaceVerifyRequest $request): FaceVerifyResult
    {
        if (str_contains($request->probeContents, self::MARQUEUR_DIFFERE)) {
            return new FaceVerifyResult(
                outcome: FaceVerifyResult::PENDING,
                externalCheckId: 'mock-check-'.$this->identite($request->probeContents),
                raw: ['provider' => 'mock'],
            );
        }

        $vivacite = str_contains($request->probeContents, self::MARQUEUR_VIVACITE_RATEE)
            ? FaceVerifyResult::LIVENESS_FAIL
            : FaceVerifyResult::LIVENESS_PASS;

        /*
         * Sans référence, on ne peut rien comparer — et on ne l'invente pas. C'est le cas d'un
         * profil dont le fichier de référence a disparu du disque : le contrôle est en erreur,
         * pas raté. La nuance compte : un échec compte dans les blocages, une erreur non.
         */
        if ($request->referenceContents === null) {
            return new FaceVerifyResult(
                outcome: FaceVerifyResult::FAILED,
                liveness: $vivacite,
                failureReason: 'reference_missing',
                raw: ['provider' => 'mock'],
            );
        }

        $memePersonne = $this->identite($request->probeContents) === $this->identite($request->referenceContents);
        $score = $memePersonne ? self::SCORE_MEME_PERSONNE : self::SCORE_AUTRE_PERSONNE;

        return new FaceVerifyResult(
            outcome: $memePersonne ? FaceVerifyResult::PASSED : FaceVerifyResult::FAILED,
            score: $score,
            liveness: $vivacite,
            externalCheckId: 'mock-check-'.$this->identite($request->probeContents),
            failureReason: $memePersonne ? null : 'score_below_threshold',
            raw: ['provider' => 'mock', 'score' => $score],
        );
    }

    public function fetchVerification(string $externalCheckId): FaceVerifyResult
    {
        /*
         * Le bouchon conclut toujours favorablement au second passage : c'est ce qui permet de
         * jouer un verdict différé de bout en bout dans un test, sans horloge ni file d'attente.
         */
        return new FaceVerifyResult(
            outcome: FaceVerifyResult::PASSED,
            score: self::SCORE_MEME_PERSONNE,
            liveness: FaceVerifyResult::LIVENESS_PASS,
            externalCheckId: $externalCheckId,
            raw: ['provider' => 'mock', 'resolved' => true],
        );
    }

    public function compareWithDocument(FaceDocumentCompareRequest $request): FaceCompareResult
    {
        if (str_contains($request->documentContents, self::MARQUEUR_ILLISIBLE)) {
            return FaceCompareResult::inconclusive('document_unreadable');
        }

        $memePersonne = $this->identite($request->documentContents) === $this->identite($request->referenceContents);
        $score = $memePersonne ? 89.0 : 14.0;

        return new FaceCompareResult(
            conclusive: true,
            score: $score,
            reason: $memePersonne ? null : 'portrait_mismatch',
            raw: ['provider' => 'mock', 'score' => $score],
        );
    }

    /**
     * L'identité portée par une image : le marqueur s'il existe, l'empreinte du contenu sinon.
     */
    private function identite(string $contenu): string
    {
        $position = strpos($contenu, self::MARQUEUR_IDENTITE);

        if ($position === false) {
            return substr(hash('sha256', $contenu), 0, 16);
        }

        $reste = substr($contenu, $position + strlen(self::MARQUEUR_IDENTITE));

        // Le marqueur s'arrête au premier caractère qui n'appartient pas à un identifiant.
        preg_match('/^[A-Za-z0-9_.-]+/', $reste, $correspondance);

        return $correspondance[0] ?? 'inconnu';
    }
}
