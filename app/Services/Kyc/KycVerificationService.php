<?php

namespace App\Services\Kyc;

use App\Events\Kyc\KycCompleted;
use App\Events\Kyc\KycRejected;
use App\Events\Kyc\KycStarted;
use App\Models\KycCheck;
use App\Models\KycVerification;
use App\Models\User;
use App\Notifications\Kyc\KycCompletedNotification;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class KycVerificationService
{
    public function __construct(protected KycProviderInterface $provider) {}

    public function start(User $user, ?string $countryCode = null, array $checks = []): KycVerification
    {
        $countryCode = strtoupper($countryCode ?? $this->detectCountryCode($user) ?? 'BE');

        if (empty($checks)) {
            $checks = (array) Config::get('kyc.standard_checks', []);
        }

        return DB::transaction(function () use ($user, $countryCode, $checks) {
            $verification = KycVerification::create([
                'user_id' => $user->id,
                'provider' => $this->provider->name(),
                'status' => KycVerification::STATUS_PENDING,
                'decision' => KycVerification::DECISION_PENDING,
                'country_code' => $countryCode,
                'requested_checks' => $checks,
                'started_at' => now(),
            ]);

            try {
                $result = $this->provider->startVerification(new KycStartRequest(
                    user: $user,
                    countryCode: $countryCode,
                    requestedChecks: $checks,
                ));

                $verification->update([
                    'external_applicant_id' => $result->externalApplicantId,
                    'external_check_id' => $result->externalCheckId,
                    'status' => KycVerification::STATUS_IN_REVIEW,
                    'metadata' => array_merge((array) $verification->metadata, [
                        'hosted_flow_url' => $result->hostedFlowUrl,
                        'provider_raw' => $result->raw,
                    ]),
                ]);
            } catch (\Throwable $e) {
                $verification->update([
                    'status' => KycVerification::STATUS_CANCELLED,
                    'metadata' => array_merge((array) $verification->metadata, [
                        'start_error' => $e->getMessage(),
                    ]),
                ]);
                Log::error('KycVerificationService::start failed', [
                    'user_id' => $user->id,
                    'provider' => $this->provider->name(),
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            if ($profile = $user->providerProfile) {
                $profile->forceFill([
                    'kyc_provider' => $this->provider->name(),
                    'kyc_external_applicant_id' => $verification->external_applicant_id,
                    'kyc_last_verification_id' => $verification->id,
                ])->save();
            }

            ActivityLogger::log('kyc.started', $verification, [
                'user_id' => $user->id,
                'provider' => $this->provider->name(),
                'country_code' => $countryCode,
            ]);

            KycStarted::dispatch($verification);

            return $verification->fresh();
        });
    }

    public function syncStatus(KycVerification $verification): KycVerification
    {
        if ($verification->isFinal()) {
            return $verification;
        }

        $statusResult = $this->provider->fetchStatus($verification);
        $this->applyStatusResult($verification, $statusResult);

        return $verification->fresh();
    }

    public function applyWebhookPayload(array $payload): ?KycVerification
    {
        // M3 / B5 — un événement sans identifiant de ressource ne DÉSIGNE personne.
        // L'ancien code enveloppait la contrainte dans un `where(function ($q) { if
        // ($resourceId) { ... } })` : sans identifiant, la fermeture n'ajoutait AUCUNE
        // condition et le `latest('id')` retombait sur la vérification la plus récente
        // du provider. Un événement anonyme faisait donc passer en « vérifié » le KYC
        // de quelqu'un d'autre, sans même connaître son identifiant. On refuse.
        $resourceId = $this->extractResourceId($payload);

        if ($resourceId === null) {
            Log::warning('KycVerificationService: événement webhook sans identifiant de ressource — refusé', [
                'provider' => $this->provider->name(),
                'cles_payload' => array_keys($payload),
            ]);

            return null;
        }

        $result = $this->provider->mapWebhookEvent($payload);
        if (! $result) {
            return null;
        }

        $verification = KycVerification::query()
            ->where('provider', $this->provider->name())
            ->where(function ($q) use ($resourceId) {
                $q->where('external_check_id', $resourceId)
                    ->orWhere('external_applicant_id', $resourceId);
            })
            ->latest('id')
            ->first();

        if (! $verification) {
            return null;
        }

        $this->applyStatusResult($verification, $result);

        return $verification->fresh();
    }

    /**
     * Identifiant de la ressource visée par l'événement. Rend null dès que la charge
     * utile n'en porte pas un exploitable : aucun repli implicite n'est toléré.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractResourceId(array $payload): ?string
    {
        $candidat = $payload['payload']['object']['id']
            ?? $payload['object']['id']
            ?? $payload['check_id']
            ?? null;

        if (is_int($candidat)) {
            $candidat = (string) $candidat;
        }

        if (! is_string($candidat)) {
            return null;
        }

        $candidat = trim($candidat);

        return $candidat === '' ? null : $candidat;
    }

    public function approveManually(KycVerification $verification, User $admin, ?string $note = null): KycVerification
    {
        return DB::transaction(function () use ($verification, $admin, $note) {
            $verification->update([
                'status' => KycVerification::STATUS_CLEAR,
                'decision' => KycVerification::DECISION_APPROVED,
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
                'completed_at' => $verification->completed_at ?? now(),
                'metadata' => array_merge((array) $verification->metadata, [
                    'manual_review_note' => $note,
                ]),
            ]);

            $this->markProviderApproved($verification);

            ActivityLogger::log('kyc.manual_approved', $verification, [
                'admin_user_id' => $admin->id,
            ]);

            KycCompleted::dispatch($verification);
            $this->notifyOutcome($verification);

            return $verification->fresh();
        });
    }

    public function rejectManually(KycVerification $verification, User $admin, string $reason): KycVerification
    {
        return DB::transaction(function () use ($verification, $admin, $reason) {
            $verification->update([
                'status' => KycVerification::STATUS_REJECTED,
                'decision' => KycVerification::DECISION_REJECTED,
                'rejection_reason' => $reason,
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
                'completed_at' => now(),
            ]);

            if ($profile = $verification->user?->providerProfile) {
                $profile->forceFill([
                    'verification_status' => 'rejected',
                ])->save();
            }

            ActivityLogger::log('kyc.manual_rejected', $verification, [
                'admin_user_id' => $admin->id,
                'reason' => $reason,
            ]);

            KycRejected::dispatch($verification);
            $this->notifyOutcome($verification);

            return $verification->fresh();
        });
    }

    protected function applyStatusResult(KycVerification $verification, KycStatusResult $result): void
    {
        DB::transaction(function () use ($verification, $result) {
            $verification->update([
                'status' => $result->status,
                'decision' => $result->decision,
                'score' => $result->score,
                'rejection_reason' => $result->rejectionReason,
                'result_summary' => array_merge((array) $verification->result_summary, [
                    'last_sync_at' => now()->toIso8601String(),
                    'raw' => $result->raw,
                ]),
                'completed_at' => in_array($result->status, KycVerification::FINAL_STATUSES, true)
                    ? ($verification->completed_at ?? now())
                    : $verification->completed_at,
            ]);

            foreach ($result->checks as $check) {
                KycCheck::updateOrCreate(
                    [
                        'kyc_verification_id' => $verification->id,
                        'check_type' => $check['type'],
                    ],
                    [
                        'result' => $check['result'],
                        'sub_result' => $check['sub_result'] ?? null,
                        'external_id' => $check['external_id'] ?? null,
                        'confidence' => $check['confidence'] ?? null,
                        'breakdown' => $check['breakdown'] ?? null,
                        'checked_at' => now(),
                    ]
                );
            }

            $shouldAutoApprove = (bool) Config::get('kyc.auto_approve_on_clear', true);
            $minScore = (float) Config::get('kyc.min_score_for_auto_approve', 0.7);

            $hasScoreAboveMin = $result->score === null || $result->score >= $minScore;

            if ($shouldAutoApprove && $result->decision === KycVerification::DECISION_APPROVED && $hasScoreAboveMin) {
                $this->markProviderApproved($verification);
                KycCompleted::dispatch($verification);
                $this->notifyOutcome($verification);
            } elseif ($result->decision === KycVerification::DECISION_REJECTED) {
                if ($profile = $verification->user?->providerProfile) {
                    $profile->forceFill([
                        'verification_status' => 'rejected',
                    ])->save();
                }
                KycRejected::dispatch($verification);
                $this->notifyOutcome($verification);
            }
        });
    }

    protected function markProviderApproved(KycVerification $verification): void
    {
        if (! $profile = $verification->user?->providerProfile) {
            return;
        }

        $attrs = [
            'verification_status' => 'verified',
            'kyc_completed_at' => now(),
            'kyc_score' => $verification->score,
        ];

        // verified_at est legacy/optionnel selon le schema ; on l'ajoute seulement
        // si la colonne existe (certaines installations n'ont jamais migré).
        if (Schema::hasColumn('provider_profiles', 'verified_at')) {
            $attrs['verified_at'] = $profile->verified_at ?? now();
        }

        $profile->forceFill($attrs)->save();
    }

    protected function notifyOutcome(KycVerification $verification): void
    {
        try {
            if ($verification->user) {
                $verification->user->notify(new KycCompletedNotification($verification));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function detectCountryCode(User $user): ?string
    {
        $code = $user->metadata['country_code']
            ?? $user->metadata['current_country_code']
            ?? null;

        if (is_string($code)) {
            return strtoupper($code);
        }

        $org = $user->organizationAccount ?? null;
        if ($org && isset($org->country_code)) {
            return strtoupper($org->country_code);
        }

        return null;
    }
}
