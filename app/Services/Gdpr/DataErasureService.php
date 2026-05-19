<?php

namespace App\Services\Gdpr;

use App\Models\GdprDataRequest;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Droit à l'oubli (art. 17 RGPD).
 *
 * Stratégie : ANONYMISATION (pas hard-delete) pour préserver :
 *   - Cohérence comptable (factures, payouts gardent leur FK user_id)
 *   - Audit/traçabilité (logs ne perdent pas leur attribution)
 *   - Obligations légales de conservation (10 ans pour factures BE/FR)
 *
 * Workflow:
 *   1. schedule() — crée request en `awaiting_grace_period` (30j par défaut)
 *   2. confirm() — user re-confirme par email (optionnel)
 *   3. execute() — après grace period : anonymise + soft-marks
 *   4. cancel() — user/admin annule avant grace expiry
 *
 * Données préservées (anonymisées) : bookings, payments, invoices, ratings
 * (avec client.name remplacé), audit_logs (avec user_id préservé mais user
 * affiché comme "Utilisateur supprimé").
 *
 * Données purgées : phone, email réel, photo, metadata sensible.
 */
class DataErasureService
{
    public function schedule(User $user, ?string $reason = null, ?array $context = null): GdprDataRequest
    {
        $gracePeriodDays = (int) config('gdpr.erasure_grace_period_days', 30);

        $request = GdprDataRequest::create([
            'user_id' => $user->id,
            'type' => GdprDataRequest::TYPE_ERASURE,
            'status' => GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
            'reference' => $this->generateReference(),
            'reason' => $reason,
            'requested_at' => now(),
            'confirmed_at' => now(),
            'grace_period_ends_at' => now()->addDays($gracePeriodDays),
            'ip_address' => $context['ip'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'metadata' => $context['metadata'] ?? null,
        ]);

        $user->forceFill([
            'deletion_scheduled_at' => $request->grace_period_ends_at,
            'last_gdpr_action_at' => now(),
        ])->save();

        ActivityLogger::log('gdpr.erasure_scheduled', $request, [
            'user_id' => $user->id,
            'grace_period_ends_at' => $request->grace_period_ends_at?->toIso8601String(),
        ]);

        return $request;
    }

    public function cancel(GdprDataRequest $request, ?User $actor = null, ?string $reason = null): GdprDataRequest
    {
        if (! in_array($request->status, [
            GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
            GdprDataRequest::STATUS_AWAITING_CONFIRMATION,
        ], true)) {
            return $request;
        }

        $request->update([
            'status' => GdprDataRequest::STATUS_CANCELLED,
            'admin_response' => $reason,
            'processed_by_user_id' => $actor?->id,
        ]);

        if ($request->user) {
            $request->user->forceFill(['deletion_scheduled_at' => null])->save();
        }

        ActivityLogger::log('gdpr.erasure_cancelled', $request, [
            'user_id' => $request->user_id,
            'actor_user_id' => $actor?->id,
        ]);

        return $request->fresh();
    }

    public function execute(GdprDataRequest $request): GdprDataRequest
    {
        if (! $request->isReadyForExecution()) {
            throw new \RuntimeException('Erasure request not ready for execution (grace period not over or wrong status).');
        }

        return DB::transaction(function () use ($request) {
            $this->anonymizeUser($request->user);

            $request->update([
                'status' => GdprDataRequest::STATUS_FULFILLED,
                'fulfilled_at' => now(),
            ]);

            ActivityLogger::log('gdpr.erasure_executed', $request, [
                'user_id' => $request->user_id,
                'reference' => $request->reference,
            ]);

            return $request->fresh();
        });
    }

    public function anonymizeUser(User $user): void
    {
        $anonEmail = str_replace('{id}', (string) $user->id, (string) config('gdpr.anonymized_email_template', 'deleted_{id}@anonymized.cleanux'));
        $anonName = (string) config('gdpr.anonymized_name', 'Utilisateur supprimé');

        $user->forceFill([
            'name' => $anonName,
            'email' => $anonEmail,
            'phone' => null,
            'tva_number' => null,
            'profile_photo_path' => null,
            'metadata' => null,
            'status' => 'deleted',
            'is_active' => false,
            'anonymized_at' => now(),
            'last_gdpr_action_at' => now(),
        ])->save();

        // Anonymiser les profils liés
        if ($user->customerProfile) {
            $user->customerProfile->forceFill([
                'plan_status' => 'deleted',
            ])->save();
        }

        if ($user->providerProfile) {
            $user->providerProfile->forceFill([
                'status' => 'inactive',
                'bio' => null,
                'photo_path' => null,
            ])->save();
        }

        // Supprimer les tokens API actifs pour révoquer l'accès immédiatement
        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->delete();
        }
    }

    public function restrictProcessing(User $user, ?string $reason = null): GdprDataRequest
    {
        $request = GdprDataRequest::create([
            'user_id' => $user->id,
            'type' => GdprDataRequest::TYPE_RESTRICTION,
            'status' => GdprDataRequest::STATUS_FULFILLED,
            'reference' => $this->generateReference(),
            'reason' => $reason,
            'requested_at' => now(),
            'fulfilled_at' => now(),
        ]);

        $user->forceFill([
            'processing_restricted_at' => now(),
            'last_gdpr_action_at' => now(),
        ])->save();

        ActivityLogger::log('gdpr.processing_restricted', $request, [
            'user_id' => $user->id,
        ]);

        return $request;
    }

    public function liftRestriction(User $user, ?User $actor = null): void
    {
        $user->forceFill([
            'processing_restricted_at' => null,
            'last_gdpr_action_at' => now(),
        ])->save();

        ActivityLogger::log('gdpr.processing_unrestricted', $user, [
            'actor_user_id' => $actor?->id,
        ]);
    }

    protected function generateReference(): string
    {
        $prefix = (string) config('gdpr.reference_prefix', 'GDPR');
        do {
            $candidate = $prefix . '-' . strtoupper(\Illuminate\Support\Str::random(10));
        } while (GdprDataRequest::where('reference', $candidate)->exists());

        return $candidate;
    }
}
