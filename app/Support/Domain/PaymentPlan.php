<?php

namespace App\Support\Domain;

/**
 * Comment le client règle : tout retenu, ou acompte puis solde.
 *
 * Le défaut est la RETENUE INTÉGRALE — rien n'est débité avant l'intervention, la somme est
 * seulement bloquée. C'est ce qui se comprend le mieux : « vous ne payez qu'après ».
 *
 * L'acompte existe pour les gros montants, où bloquer deux mille euros sur une carte pendant une
 * semaine est intenable pour le client — sa limite de carte est mangée par une somme qu'il n'a
 * même pas dépensée.
 */
class PaymentPlan
{
    /** Tout est bloqué maintenant, débité à la fin. Rien ne quitte le compte avant. */
    public const FULL = 'full';

    /** Une part est débitée maintenant, le reste bloqué et débité à la fin. */
    public const DEPOSIT = 'deposit';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::FULL, self::DEPOSIT];
    }

    public static function isValid(?string $plan): bool
    {
        return in_array($plan, self::all(), true);
    }

    public static function label(string $plan): string
    {
        return match ($plan) {
            self::FULL => 'Tout à la fin',
            self::DEPOSIT => 'Acompte puis solde',
            default => $plan,
        };
    }
}
