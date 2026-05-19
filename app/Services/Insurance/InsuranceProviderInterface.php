<?php

namespace App\Services\Insurance;

interface InsuranceProviderInterface
{
    public function name(): string;

    public function purchase(InsurancePurchaseRequest $request): InsurancePurchaseResult;

    public function cancelPolicy(string $externalId): InsuranceCancelResult;

    public function fileClaim(ClaimFilingRequest $request): ClaimFilingResult;

    /**
     * Vérifie la signature d'un webhook + retourne payload parsé.
     *
     * @return array<string,mixed>
     */
    public function verifyWebhook(string $payload, array $headers): array;

    public function mapWebhookEvent(array $payload): ?InsuranceWebhookUpdate;
}
