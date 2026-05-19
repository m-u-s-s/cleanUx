<?php

namespace App\Http\Middleware;

use App\Services\I18n\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware SetLocale data-driven (Phase i18n v2).
 *
 * Délègue à LocaleResolver et applique :
 *   - app()->setLocale()
 *   - setlocale(LC_TIME, ...) selon code (best-effort)
 *   - Carbon::setLocale()
 *   - Cookie 1 an pour visiteurs anonymes
 *
 * Les constantes SUPPORTED et DEFAULT sont conservées pour rétrocompat
 * (du code legacy peut les référencer) — mais elles dérivent maintenant
 * de config('i18n').
 */
class SetLocale
{
    /**
     * @deprecated Conservée pour rétrocompat ; la source de vérité est config/i18n.php.
     *             Pour le runtime, utiliser app(LocaleResolver::class)->supportedCodes().
     */
    public const SUPPORTED = ['fr', 'nl', 'en', 'es', 'it', 'de'];

    /**
     * @deprecated Conservée pour rétrocompat ; voir config/i18n.php.
     */
    public const DEFAULT = 'fr';

    public function __construct(protected LocaleResolver $resolver)
    {
    }

    public static function supported(): array
    {
        return app(LocaleResolver::class)->supportedCodes();
    }

    public static function defaultLocale(): string
    {
        return app(LocaleResolver::class)->default();
    }

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolver->resolveFromRequest($request);

        App::setLocale($locale);

        @setlocale(LC_TIME, $this->setlocaleVariants($locale));

        if (class_exists(\Carbon\Carbon::class)) {
            \Carbon\Carbon::setLocale($locale);
        }

        $response = $next($request);

        if (method_exists($response, 'withCookie')) {
            $response->withCookie(cookie('locale', $locale, 60 * 24 * 365));
        }

        return $response;
    }

    /**
     * @return array<string>
     */
    protected function setlocaleVariants(string $locale): array
    {
        return match ($locale) {
            'nl' => ['nl_BE.UTF-8', 'nl_BE', 'nl_NL.UTF-8', 'nl'],
            'en' => ['en_US.UTF-8', 'en_US', 'en_GB.UTF-8', 'en'],
            'es' => ['es_ES.UTF-8', 'es_ES', 'es'],
            'it' => ['it_IT.UTF-8', 'it_IT', 'it'],
            'de' => ['de_DE.UTF-8', 'de_DE', 'de'],
            'pt' => ['pt_PT.UTF-8', 'pt_PT', 'pt_BR.UTF-8', 'pt'],
            default => ['fr_BE.UTF-8', 'fr_BE', 'fr_FR.UTF-8', 'fr'],
        };
    }
}
