<?php

namespace App\Models\Concerns;

use App\Support\Domain\BookingStatus;

trait ResetsNotificationTracking
{
    public function resetNotificationTrackingIfNeeded(array $original = []): void
    {
        $dateChanged = array_key_exists('date', $original) && $original['date'] != $this->date;
        $heureChanged = array_key_exists('heure', $original) && $original['heure'] != $this->heure;
        $statusChanged = array_key_exists('status', $original) && $original['status'] !== $this->status;
        $prioriteChanged = array_key_exists('priorite', $original) && $original['priorite'] !== $this->priorite;

        if ($dateChanged || $heureChanged) {
            $this->rappel_24h_envoye_at = null;
            $this->rappel_2h_envoye_at = null;
        }

        if ($statusChanged && BookingStatus::requiresReminderReset((string) $this->status)) {
            $this->rappel_24h_envoye_at = null;
            $this->rappel_2h_envoye_at = null;
        }

        if ($prioriteChanged && $this->priorite === 'urgente') {
            $this->alerte_urgence_envoyee_at = null;
        }

        if (($dateChanged || $heureChanged) && $this->priorite === 'urgente') {
            $this->alerte_urgence_envoyee_at = null;
        }
    }

    public function isFinalStatus(): bool
    {
        return in_array($this->status, BookingStatus::final(), true);
    }

    public function canStillBeEditedByClient(): bool
    {
        return in_array($this->status, BookingStatus::clientEditable(), true);
    }
}
