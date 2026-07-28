<?php

namespace App\Http\Controllers\Api\Concerns;

use DateTimeInterface;

/**
 * Normalisation des date/heure de réservation pour les payloads API mobiles.
 *
 * Booking caste `scheduled_date` en `date` et `scheduled_time` en `datetime:H:i` : ces formats
 * ne s'appliquent QUE lorsque le modèle lui-même est sérialisé. Placés tels quels dans un
 * tableau rendu par response()->json(), les Carbon passent par leur propre jsonSerialize() et
 * ressortent en ISO-8601 complet ("2026-06-15T00:00:00.000000Z"), là où les écrans mobiles
 * affichent brut « {scheduled_date} à {scheduled_time} ».
 */
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
