<?php

namespace App\Services\FaceCheck;

use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceProfile;

/** QUAND LE PROCHAIN CONTRÔLE TOMBE — et pourquoi il tombe maintenant. */
class FaceCheckScheduler
{
    public function __construct(
        private readonly FaceCheckSettings $settings,
    ) {}

    /** Tire au sort la prochaine échéance et la pose. */
    public function scheduleNext(ProviderFaceProfile $profile): ProviderFaceProfile
    {
        $min = $this->settings->minHours() * 3600;
        $max = $this->settings->maxHours() * 3600;

        $profile->forceFill([
            'next_check_due_at' => now()->addSeconds(random_int($min, $max)),
        ])->save();

        return $profile;
    }

    /** Pourquoi un contrôle est dû maintenant, ou `null` s'il ne l'est pas. */
    public function dueTrigger(ProviderFaceProfile $profile, ?string $deviceName = null): ?string
    {
        if (! $profile->isEnrolled() || $profile->isBlocked()) {
            return null;
        }

        if ($profile->consecutive_failures > 0) {
            return ProviderFaceCheck::TRIGGER_RISK_FAILURES;
        }

        if ($this->appareilInconnu($profile, $deviceName)) {
            return ProviderFaceCheck::TRIGGER_RISK_DEVICE;
        }

        if ($this->abandonsRepetes($profile)) {
            return ProviderFaceCheck::TRIGGER_RISK_ABANDONS;
        }

        if ($profile->isCheckDue()) {
            return ProviderFaceCheck::TRIGGER_INTERVAL;
        }

        return null;
    }

    /** UN NOUVEL APPAREIL EST UN SIGNAL, PAS UNE FAUTE. */
    private function appareilInconnu(ProviderFaceProfile $profile, ?string $deviceName): bool
    {
        if (! filled($deviceName)) {
            return false;
        }

        $dernierReussi = ProviderFaceCheck::query()
            ->where('provider_face_profile_id', $profile->id)
            ->where('status', ProviderFaceCheck::STATUS_PASSED)
            ->whereNotNull('device_name')
            ->latest('answered_at')
            ->first();

        if ($dernierReussi === null) {
            return false;
        }

        return $dernierReussi->device_name !== $deviceName;
    }

    private function abandonsRepetes(ProviderFaceProfile $profile): bool
    {
        $abandons = ProviderFaceCheck::query()
            ->where('provider_face_profile_id', $profile->id)
            ->where('status', ProviderFaceCheck::STATUS_ABANDONED)
            ->where('requested_at', '>=', now()->subDays($this->settings->abandonWindowDays()))
            ->count();

        return $abandons >= $this->settings->abandonThreshold();
    }
}
