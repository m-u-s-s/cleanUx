<?php

namespace App\Http\Controllers\Api\Concerns;

use DateTimeInterface;

/** Normalisation des date/heure de réservation pour les payloads API mobiles. */
trait FormatsBookingSchedule
{
    protected function formatScheduledDate(mixed $value): ?string
    {
        return $this->formatTemporal($value, 'Y-m-d');
    }

    protected function formatScheduledTime(mixed $value): ?string
    {
        return $this->formatTemporal($value, 'H:i');
    }

    private function formatTemporal(mixed $value, string $format): ?string
    {
        if ($value === null) {
            return null;
        }

        // Défensif : selon les colonnes sélectionnées et l'origine du modèle, la valeur peut
        // arriver déjà sous forme de chaîne (pas de cast appliqué). On ne la reformate pas au
        // hasard dans ce cas — mieux vaut la rendre telle quelle que de deviner.
        return $value instanceof DateTimeInterface ? $value->format($format) : (string) $value;
    }
}
