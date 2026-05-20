<?php

namespace App\Services\KybV2;

use App\Models\BusinessEntity;
use App\Models\BusinessSanctionsCheck;
use App\Models\BusinessVerification;
use App\Models\User;
use App\Services\KybV2\Contracts\BusinessVerificationProviderContract;
use App\Services\KybV2\Contracts\SanctionsScreeningProviderContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BusinessOnboardingService
{
    public function __construct(
        protected BusinessVerificationProviderContract $verificationProvider,
        protected SanctionsScreeningProviderContract $sanctionsProvider,
        protected RiskScoreEngine $riskEngine,
    ) {}

    /**
     * Crée (ou retourne) une BusinessEntity pour un user/contact.
     * Idempotent par (country_code, identifier_type, identifier_value).
     */
    public function startVerification(array $payload, ?User $owner = null): BusinessEntity
    {
        $required = ['legal_name', 'country_code', 'identifier_type', 'identifier_value'];
        foreach ($required as $f) {
            if (empty($payload[$f])) {
                throw ValidationException::withMessages([$f => ["Champ {$f} requis."]]);
            }
        }
        $country = strtoupper((string) $payload['country_code']);
        $allowedByCountry = (array) config('kyb_v2.identifier_types_by_country.' . $country, []);
        $idType = strtolower((string) $payload['identifier_type']);
        if (! empty($allowedByCountry) && ! in_array($idType, $allowedByCountry, true)) {
            throw ValidationException::withMessages([
                'identifier_type' => ["Type {$idType} non supporté pour pays {$country}."],
            ]);
        }
        $idValue = preg_replace('/\s+/', '', (string) $payload['identifier_value']);

        $existing = BusinessEntity::query()
            ->where('country_code', $country)
            ->where('identifier_type', $idType)
            ->where('identifier_value', $idValue)
            ->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($payload, $country, $idType, $idValue, $owner) {
            return BusinessEntity::query()->create([
                'code' => BusinessEntity::generateCode(),
                'legal_name' => (string) $payload['legal_name'],
                'trade_name' => $payload['trade_name'] ?? null,
                'country_code' => $country,
                'identifier_type' => $idType,
                'identifier_value' => $idValue,
                'vat_id' => $payload['vat_id'] ?? null,
                'legal_form' => $payload['legal_form'] ?? null,
                'registered_address' => $payload['registered_address'] ?? null,
                'incorporation_date' => $payload['incorporation_date'] ?? null,
                'owner_user_id' => $owner?->id,
                'contact_email' => $payload['contact_email'] ?? null,
                'contact_user_id' => $payload['contact_user_id'] ?? null,
                'status' => BusinessEntity::STATUS_PENDING,
                'metadata' => $payload['metadata'] ?? null,
            ]);
        });
    }

    /**
     * Lance les vérifs provider (identity + vat si présent). Soft-fail.
     */
    public function runVerifications(BusinessEntity $entity): BusinessEntity
    {
        // identity
        $this->runSingleCheck($entity, 'identity', function () use ($entity) {
            return $this->verificationProvider->verifyIdentifier(
                $entity->identifier_type,
                $entity->identifier_value,
                $entity->country_code,
            );
        });

        // vat (si vat_id fourni)
        if ($entity->vat_id) {
            $this->runSingleCheck($entity, 'tax_validity', function () use ($entity) {
                return $this->verificationProvider->verifyVatId($entity->vat_id, $entity->country_code);
            });
        }

        return $this->refreshRiskAndStatus($entity->fresh());
    }

    protected function runSingleCheck(BusinessEntity $entity, string $checkType, callable $resolver): BusinessVerification
    {
        $idempotencyKey = $entity->id . ':' . $checkType . ':' . md5((string) $entity->identifier_value);

        $existing = BusinessVerification::query()
            ->where('idempotency_key', $idempotencyKey)
            ->fresh()
            ->first();
        if ($existing) {
            return $existing;
        }

        try {
            /** @var VerificationResult $result */
            $result = $resolver();
        } catch (\Throwable $e) {
            Log::warning('[kyb_v2] verification failed', ['error' => $e->getMessage()]);
            $result = new VerificationResult(
                false, $this->verificationProvider->name(), $checkType,
                error: 'exception:' . mb_substr($e->getMessage(), 0, 200),
            );
        }

        $cacheDays = (int) config('kyb_v2.verification_cache_days', 90);
        return BusinessVerification::query()->updateOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'entity_id' => $entity->id,
                'provider' => $result->provider,
                'check_type' => $checkType,
                'status' => $result->success
                    ? BusinessVerification::STATUS_SUCCESS
                    : ($result->error ? BusinessVerification::STATUS_FAILED : BusinessVerification::STATUS_ERROR),
                'matched_value' => $result->matchedValue,
                'response_payload' => $result->payload,
                'last_error' => $result->error,
                'checked_at' => now(),
                'expires_at' => $cacheDays > 0 ? now()->addDays($cacheDays) : null,
            ],
        );
    }

    /**
     * Lance sanctions screening contre toutes les listes config.
     */
    public function runSanctionsScreening(BusinessEntity $entity): BusinessEntity
    {
        $lists = (array) config('kyb_v2.sanctions_lists', ['eu', 'us_ofac']);
        $cacheDays = (int) config('kyb_v2.sanctions_cache_days', 30);

        foreach ($lists as $list) {
            try {
                $result = $this->sanctionsProvider->screen($entity->legal_name, $list, $entity->country_code);
                BusinessSanctionsCheck::query()->updateOrCreate(
                    ['entity_id' => $entity->id, 'list_name' => $list],
                    [
                        'status' => $result->hasMatch
                            ? BusinessSanctionsCheck::STATUS_MATCH
                            : BusinessSanctionsCheck::STATUS_CLEAR,
                        'match_count' => $result->matchCount,
                        'match_payload' => $result->matches,
                        'provider' => $result->provider,
                        'checked_at' => now(),
                        'expires_at' => $cacheDays > 0 ? now()->addDays($cacheDays) : null,
                    ],
                );
            } catch (\Throwable $e) {
                Log::warning('[kyb_v2] sanctions screening failed', ['list' => $list, 'error' => $e->getMessage()]);
                BusinessSanctionsCheck::query()->updateOrCreate(
                    ['entity_id' => $entity->id, 'list_name' => $list],
                    [
                        'status' => BusinessSanctionsCheck::STATUS_ERROR,
                        'provider' => $this->sanctionsProvider->name(),
                        'checked_at' => now(),
                        'match_payload' => ['error' => $e->getMessage()],
                    ],
                );
            }
        }

        return $this->refreshRiskAndStatus($entity->fresh());
    }

    /**
     * Recompute risk score + propose un status (sans auto-approuver sauf si config).
     */
    public function refreshRiskAndStatus(BusinessEntity $entity): BusinessEntity
    {
        $risk = $this->riskEngine->compute($entity);
        $entity->risk_score = $risk['score'];
        $entity->risk_level = $risk['level'];

        // auto-status logic
        $hasSanctionsMatch = in_array('sanctions_match', $risk['reasons'], true);
        $hasPep = in_array('pep_owner', $risk['reasons'], true);
        if ($entity->isCriticalRisk() || $hasSanctionsMatch || $hasPep) {
            // sanctions/pep/critical → toujours forcer review humaine, peu importe le score brut
            if ($entity->status === BusinessEntity::STATUS_PENDING) {
                $entity->status = BusinessEntity::STATUS_NEEDS_REVIEW;
            }
        }

        // auto-approve si config + score OK + identité vérifiée + sanctions clear
        $autoApprove = (bool) config('kyb_v2.auto_approve_enabled', false);
        if ($autoApprove
            && $entity->status === BusinessEntity::STATUS_PENDING
            && $risk['score'] <= (float) config('kyb_v2.auto_approve_score_max', 30)
        ) {
            $identityOk = $entity->verifications()
                ->where('check_type', 'identity')
                ->where('status', BusinessVerification::STATUS_SUCCESS)
                ->exists();
            $sanctionsClear = ! $entity->sanctionsChecks()->matches()->exists();
            if ($identityOk && $sanctionsClear) {
                $entity->status = BusinessEntity::STATUS_VERIFIED;
                $entity->verified_at = now();
            }
        }

        $entity->save();
        return $entity->fresh();
    }

    public function approve(BusinessEntity $entity, ?User $admin = null): BusinessEntity
    {
        $entity->update([
            'status' => BusinessEntity::STATUS_VERIFIED,
            'verified_at' => now(),
            'verified_by_user_id' => $admin?->id,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);
        \App\Support\Audit\CriticalActionAuditor::record(
            eventType: 'kyb.entity_approved',
            context: [
                'entity_id' => $entity->id,
                'legal_name' => $entity->legal_name,
                'identifier_type' => $entity->identifier_type,
                'identifier_value' => $entity->identifier_value,
                'risk_score' => $entity->risk_score,
            ],
            subject: $entity,
            actor: $admin,
        );
        return $entity->fresh();
    }

    public function reject(BusinessEntity $entity, string $reason, ?User $admin = null): BusinessEntity
    {
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => ['Raison minimum 10 caractères.']]);
        }
        $entity->update([
            'status' => BusinessEntity::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'verified_at' => null,
            'verified_by_user_id' => null,
        ]);
        \App\Support\Audit\CriticalActionAuditor::record(
            eventType: 'kyb.entity_rejected',
            context: [
                'entity_id' => $entity->id,
                'legal_name' => $entity->legal_name,
                'reason' => $reason,
                'risk_score' => $entity->risk_score,
            ],
            subject: $entity,
            actor: $admin,
            severity: 'warning',
        );
        return $entity->fresh();
    }
}
