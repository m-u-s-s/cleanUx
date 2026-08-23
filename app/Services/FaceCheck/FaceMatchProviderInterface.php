<?php

namespace App\Services\FaceCheck;

use App\Services\FaceCheck\Data\FaceCompareResult;
use App\Services\FaceCheck\Data\FaceDocumentCompareRequest;
use App\Services\FaceCheck\Data\FaceEnrollRequest;
use App\Services\FaceCheck\Data\FaceEnrollResult;
use App\Services\FaceCheck\Data\FaceVerifyRequest;
use App\Services\FaceCheck\Data\FaceVerifyResult;

/** Le contrat d'un moteur de comparaison faciale. Quatre gestes, et pas un de plus. */
interface FaceMatchProviderInterface
{
    /** 'mock' | 'onfido' */
    public function name(): string;

    /** Enregistre le visage de référence. Rend l'identifiant côté fournisseur s'il en produit un. */
    public function enroll(FaceEnrollRequest $request): FaceEnrollResult;

    /** Vérifie un selfie pris à l'instant, avec détection de vivacité. */
    public function verify(FaceVerifyRequest $request): FaceVerifyResult;

    /** Relit un verdict différé. */
    public function fetchVerification(string $externalCheckId): FaceVerifyResult;

    /** Compare le visage de référence au portrait porté par la pièce d'identité. */
    public function compareWithDocument(FaceDocumentCompareRequest $request): FaceCompareResult;
}
