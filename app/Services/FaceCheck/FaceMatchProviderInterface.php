<?php

namespace App\Services\FaceCheck;

use App\Services\FaceCheck\Data\FaceCompareResult;
use App\Services\FaceCheck\Data\FaceDocumentCompareRequest;
use App\Services\FaceCheck\Data\FaceEnrollRequest;
use App\Services\FaceCheck\Data\FaceEnrollResult;
use App\Services\FaceCheck\Data\FaceVerifyRequest;
use App\Services\FaceCheck\Data\FaceVerifyResult;

/**
 * Le contrat d'un moteur de comparaison faciale.
 *
 * Quatre gestes, et pas un de plus. Le module ne sait rien du fournisseur : il lui demande
 * d'enrôler un visage, de vérifier un selfie, de l'apparier à une pièce d'identité, et de relire
 * un verdict qui n'était pas prêt. Tout le reste — cadence, blocage, alertes, RGPD — appartient à
 * la plateforme et ne se délègue pas.
 *
 * Ce contrat est calqué sur `App\Services\Kyc\KycProviderInterface`, qui a fait ses preuves ici :
 * une interface étroite, un bouchon déterministe, un adaptateur réel, et le choix par la config.
 */
interface FaceMatchProviderInterface
{
    /** 'mock' | 'onfido' */
    public function name(): string;

    /**
     * Enregistre le visage de référence. Rend l'identifiant côté fournisseur s'il en produit un.
     */
    public function enroll(FaceEnrollRequest $request): FaceEnrollResult;

    /**
     * Vérifie un selfie pris à l'instant, avec détection de vivacité.
     *
     * Le verdict peut être DIFFÉRÉ (`FaceVerifyResult::PENDING`) : un fournisseur réel travaille en
     * quelques secondes, pas dans le temps d'une requête HTTP. C'est `fetchVerification()` qui
     * conclut alors, et la porte reste fermée entre-temps — un verdict en attente n'est pas un
     * verdict favorable.
     */
    public function verify(FaceVerifyRequest $request): FaceVerifyResult;

    /**
     * Relit un verdict différé.
     */
    public function fetchVerification(string $externalCheckId): FaceVerifyResult;

    /**
     * Compare le visage de référence au portrait porté par la pièce d'identité.
     */
    public function compareWithDocument(FaceDocumentCompareRequest $request): FaceCompareResult;
}
