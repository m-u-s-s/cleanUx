<?php

namespace App\Services\Kyc;

use App\Models\KycVerification;

interface KycProviderInterface
{
    /**
     * Identifiant du provider ('mock', 'onfido', 'veriff', 'sumsub').
     */
    public function name(): string;

    /**
     * Démarre une vérification chez le provider externe.
     * Doit créer un "applicant" si nécessaire et renvoyer les IDs externes.
     */
    public function startVerification(KycStartRequest $request): KycStartResult;

    /**
     * Récupère le statut courant d'une vérification depuis le provider externe.
     */
    public function fetchStatus(KycVerification $verification): KycStatusResult;

    /**
     * Vérifie la signature d'un webhook entrant et retourne le payload parsé.
     * Doit throw si la signature est invalide.
     *
     * @return array<string,mixed>
     */
    public function verifyWebhook(string $payload, array $headers): array;

    /**
     * Transforme un payload webhook en KycStatusResult exploitable.
     */
    public function mapWebhookEvent(array $payload): ?KycStatusResult;
}
