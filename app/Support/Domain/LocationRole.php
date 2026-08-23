<?php

namespace App\Support\Domain;

/** Le rôle d'une question de localisation dans un parcours. */
final class LocationRole
{
    /** Le point de prise en charge — c'est LUI qui vaut adresse d'intervention. */
    public const PICKUP = 'pickup';

    /** Le point de dépose. */
    public const DROPOFF = 'dropoff';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PICKUP, self::DROPOFF];
    }

    public static function label(?string $role): string
    {
        return match ($role) {
            self::PICKUP => 'Point de départ',
            self::DROPOFF => 'Point d’arrivée',
            default => 'Localisation',
        };
    }
}
