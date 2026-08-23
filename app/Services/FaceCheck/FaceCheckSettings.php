<?php

namespace App\Services\FaceCheck;

use App\Models\PlatformModule;

/** LES RÉGLAGES, LUS AU MÊME ENDROIT PAR TOUT LE MONDE. */
class FaceCheckSettings
{
    private ?PlatformModule $module = null;

    private bool $moduleCharge = false;

    public function minHours(): int
    {
        return max(1, (int) $this->valeur('min_hours', config('face_check.interval.min_hours', 24)));
    }

    /** La borne haute ne peut pas passer sous la borne basse : un intervalle inversé ferait rendre un `random_int` inversé, qui lève. */
    public function maxHours(): int
    {
        return max($this->minHours(), (int) $this->valeur('max_hours', config('face_check.interval.max_hours', 72)));
    }

    public function checkTtlMinutes(): int
    {
        return max(1, (int) $this->valeur('check_ttl_minutes', config('face_check.check_ttl_minutes', 15)));
    }

    public function matchThreshold(): float
    {
        return (float) $this->valeur('match_threshold', config('face_check.match_threshold', 75.0));
    }

    public function idMatchThreshold(): float
    {
        return (float) $this->valeur('id_match_threshold', config('face_check.id_match_threshold', 65.0));
    }

    public function livenessRequired(): bool
    {
        return (bool) $this->valeur('liveness_required', config('face_check.liveness_required', true));
    }

    public function maxAttempts(): int
    {
        return max(1, (int) $this->valeur('max_attempts', config('face_check.max_attempts', 3)));
    }

    public function failureThreshold(): int
    {
        return max(1, (int) $this->valeur('failure_threshold', config('face_check.failure_threshold', 3)));
    }

    public function abandonThreshold(): int
    {
        return max(1, (int) $this->valeur('abandon_threshold', config('face_check.abandon.threshold', 3)));
    }

    public function abandonWindowDays(): int
    {
        return max(1, (int) $this->valeur('abandon_window_days', config('face_check.abandon.window_days', 7)));
    }

    public function abandonFraudThreshold(): int
    {
        return max(
            $this->abandonThreshold(),
            (int) $this->valeur('abandon_fraud_threshold', config('face_check.abandon.fraud_threshold', 6))
        );
    }

    public function selfieRetentionDays(): int
    {
        return max(1, (int) $this->valeur('selfie_retention_days', config('face_check.selfie_retention_days', 30)));
    }

    public function enrolmentGraceDays(): int
    {
        return max(0, (int) $this->valeur('enrolment_grace_days', config('face_check.enrolment_grace_days', 0)));
    }

    public function consentVersion(): string
    {
        return (string) config('face_check.consent_version', '1.0');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'min_hours' => $this->minHours(),
            'max_hours' => $this->maxHours(),
            'check_ttl_minutes' => $this->checkTtlMinutes(),
            'match_threshold' => $this->matchThreshold(),
            'id_match_threshold' => $this->idMatchThreshold(),
            'liveness_required' => $this->livenessRequired(),
            'max_attempts' => $this->maxAttempts(),
            'failure_threshold' => $this->failureThreshold(),
            'abandon_threshold' => $this->abandonThreshold(),
            'abandon_window_days' => $this->abandonWindowDays(),
            'abandon_fraud_threshold' => $this->abandonFraudThreshold(),
            'selfie_retention_days' => $this->selfieRetentionDays(),
            'enrolment_grace_days' => $this->enrolmentGraceDays(),
        ];
    }

    public function module(): ?PlatformModule
    {
        if (! $this->moduleCharge) {
            $this->moduleCharge = true;

            try {
                $this->module = PlatformModule::query()
                    ->where('key', (string) config('face_check.module_key', 'security.face_check'))
                    ->first();
            } catch (\Throwable $e) {
                // Table absente (installation neuve, migration en cours) : la config fait foi.
                $this->module = null;
            }
        }

        return $this->module;
    }

    public function forget(): void
    {
        $this->module = null;
        $this->moduleCharge = false;
    }

    private function valeur(string $cle, mixed $defaut): mixed
    {
        $depuisLeModule = $this->module()?->settingsValue("face_check.{$cle}");

        return $depuisLeModule ?? $defaut;
    }
}
