<?php

namespace App\Services\FaceCheck;

use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceProfile;

/**
 * QUAND LE PROCHAIN CONTRÔLE TOMBE — et pourquoi il tombe maintenant.
 *
 * LA CADENCE NE DOIT PAS ÊTRE PRÉVISIBLE. C'est le point que toutes les plateformes qui font ça
 * sérieusement soulignent : Uber écrit noir sur blanc que ses contrôles sont déclenchés « à des
 * moments différents pour éviter la prévisibilité ». Un prestataire qui saurait que le contrôle
 * tombe tous les mardis à 8 h se présenterait en personne le mardi à 8 h, et prêterait son compte
 * le reste de la semaine — le module aurait alors coûté cher pour ne rien prouver.
 *
 * D'où trois règles :
 *   1. L'échéance est tirée au sort dans [min_hours, max_hours] par le SERVEUR.
 *   2. Elle est tirée au moment du contrôle PRÉCÉDENT, jamais à la demande du client.
 *   3. Elle n'est renvoyée par AUCUNE réponse d'API.
 *
 * S'y ajoutent les déclencheurs de risque, empruntés à Uber : un nouvel appareil, des échecs
 * récents ou des abandons répétés provoquent un contrôle hors cadence.
 */
class FaceCheckScheduler
{
    public function __construct(
        private readonly FaceCheckSettings $settings,
    ) {}

    /**
     * Tire au sort la prochaine échéance et la pose.
     *
     * `random_int` et non `rand` : c'est un générateur cryptographique. Le nombre de secondes
     * n'est pas un secret en soi, mais il devient devinable si la graine l'est — et une échéance
     * devinable est une échéance contournable.
     */
    public function scheduleNext(ProviderFaceProfile $profile): ProviderFaceProfile
    {
        $min = $this->settings->minHours() * 3600;
        $max = $this->settings->maxHours() * 3600;

        $profile->forceFill([
            'next_check_due_at' => now()->addSeconds(random_int($min, $max)),
        ])->save();

        return $profile;
    }

    /**
     * Pourquoi un contrôle est dû maintenant, ou `null` s'il ne l'est pas.
     *
     * L'ordre compte : un motif de risque prime sur la cadence, parce qu'il dit quelque chose de
     * plus précis dans le journal — « nouvel appareil » se lit, « échéance atteinte » n'apprend
     * rien à l'administrateur qui enquête.
     */
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

    /**
     * UN NOUVEL APPAREIL EST UN SIGNAL, PAS UNE FAUTE.
     *
     * C'est le signal exact qu'Uber surveille : plusieurs appareils sur un même compte est le
     * marqueur du compte prêté. On ne bloque pas — un prestataire change de téléphone comme tout
     * le monde — on demande simplement un contrôle tout de suite.
     *
     * Un appareil non renseigné (session web, ancien client) ne déclenche rien : on ne fabrique
     * pas un soupçon à partir d'une absence d'information.
     */
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
