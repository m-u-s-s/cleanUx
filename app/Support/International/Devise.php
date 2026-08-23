<?php

namespace App\Support\International;

/** UNE SEULE FAÇON DE RÉPONDRE « DANS QUELLE MONNAIE ? ». */
final class Devise
{
    /** La devise de base de la plateforme. */
    public static function plateforme(): string
    {
        return self::normaliser(config('fx.base_currency')) ?? 'EUR';
    }

    /** La première devise réellement renseignée parmi les candidats, sinon celle de la plateforme. */
    public static function premiereRenseignee(?string ...$candidats): string
    {
        foreach ($candidats as $candidat) {
            $devise = self::normaliser($candidat);

            if ($devise !== null) {
                return $devise;
            }
        }

        return self::plateforme();
    }

    /** Le même choix, en minuscules — la forme qu'attend Stripe. */
    public static function pourStripe(?string ...$candidats): string
    {
        return strtolower(self::premiereRenseignee(...$candidats));
    }

    /** Un code ISO 4217 propre, ou `null` si rien d'exploitable n'a été fourni. */
    public static function normaliser(mixed $valeur): ?string
    {
        if (! is_string($valeur)) {
            return null;
        }

        $devise = strtoupper(trim($valeur));

        return strlen($devise) === 3 ? $devise : null;
    }
}
