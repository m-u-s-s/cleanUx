<?php

namespace App\Support\Validation;

use App\Services\I18n\LocaleResolver;

/**
 * La règle de validation d'une langue, DÉRIVÉE de la configuration.
 *
 * Cinq points d'entrée écrivaient `in:fr,nl,en` à la main pendant que `config/i18n.php` en
 * activait six : l'API refusait donc l'espagnol, l'italien et l'allemand que la plateforme
 * sert déjà. Une liste recopiée six fois dérive à la première langue ajoutée.
 */
final class LanguesAcceptees
{
    /** La règle `in:` prête à poser dans un tableau de validation. */
    public static function regle(): string
    {
        return 'in:'.implode(',', app(LocaleResolver::class)->supportedCodes());
    }
}
