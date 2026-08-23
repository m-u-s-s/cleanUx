<?php

namespace App\Services\FaceCheck;

use App\Models\Booking;
use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceProfile;
use App\Models\User;
use App\Services\FaceCheck\Data\FaceCheckDecision;

/** « CE PRESTATAIRE PEUT-IL ALLER CHEZ UN CLIENT MAINTENANT ? */
class FaceCheckGate
{
    public function __construct(
        private readonly FaceCheckRequirement $requirement,
        private readonly FaceCheckScheduler $scheduler,
        private readonly FaceCheckSettings $settings,
    ) {}

    /** La porte générale : mise en ligne, départ vers le client, surface API prestataire. */
    public function inspectProvider(User $provider, ?string $deviceName = null): FaceCheckDecision
    {
        if (! $this->requirement->appliesToProvider($provider)) {
            return FaceCheckDecision::ok();
        }

        return $this->verdict($provider, $deviceName);
    }

    /** La porte de la mission : acceptation d'une offre, affectation interne. */
    public function inspectForBooking(User $provider, Booking $booking): FaceCheckDecision
    {
        $soumisParLaMission = $this->requirement->appliesToBooking($booking);
        $soumisParSonProfil = $this->requirement->appliesToProvider($provider);

        if (! $soumisParLaMission && ! $soumisParSonProfil) {
            return FaceCheckDecision::ok();
        }

        return $this->verdict($provider, null);
    }

    private function verdict(User $provider, ?string $deviceName): FaceCheckDecision
    {
        $profil = ProviderFaceProfile::query()->where('user_id', $provider->id)->first();

        if ($profil === null || ! $profil->isEnrolled() || ! $profil->hasActiveConsent()) {
            return $this->enrolementRequis();
        }

        if ($profil->isBlocked()) {
            return new FaceCheckDecision(
                code: FaceCheckDecision::BLOCKED,
                message: __('face_check.gate.blocked'),
            );
        }

        // Un contrôle DÉJÀ OUVERT prime sur la cadence.
        $ouvert = ProviderFaceCheck::query()
            ->where('provider_face_profile_id', $profil->id)
            ->where('status', ProviderFaceCheck::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->latest('requested_at')
            ->first();

        if ($ouvert !== null) {
            // Répondu mais pas encore tranché par le fournisseur : on attend, porte fermée.
            if ($ouvert->answered_at !== null) {
                return new FaceCheckDecision(
                    code: FaceCheckDecision::CHECK_PENDING,
                    message: __('face_check.gate.check_pending'),
                    checkId: $ouvert->id,
                );
            }

            return new FaceCheckDecision(
                code: FaceCheckDecision::CHECK_REQUIRED,
                message: __('face_check.gate.check_required'),
                checkId: $ouvert->id,
                trigger: $ouvert->triggered_by,
            );
        }

        $motif = $this->scheduler->dueTrigger($profil, $deviceName);

        if ($motif !== null) {
            return new FaceCheckDecision(
                code: FaceCheckDecision::CHECK_REQUIRED,
                message: __('face_check.gate.check_required'),
                trigger: $motif,
            );
        }

        return FaceCheckDecision::ok();
    }

    /** LA GRÂCE SE COMPTE DEPUIS L'ALLUMAGE DU MODULE, PAS DEPUIS L'INSCRIPTION. */
    private function enrolementRequis(): FaceCheckDecision
    {
        $grace = $this->settings->enrolmentGraceDays();

        if ($grace > 0) {
            $allumage = $this->settings->module()?->updated_at;

            if ($allumage !== null && $allumage->copy()->addDays($grace)->isFuture()) {
                return FaceCheckDecision::ok();
            }
        }

        return new FaceCheckDecision(
            code: FaceCheckDecision::ENROLMENT_REQUIRED,
            message: __('face_check.gate.enrolment_required'),
        );
    }
}
