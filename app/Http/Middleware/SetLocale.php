<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    protected array $supported = ['fr', 'nl', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);
        setlocale(LC_TIME, match ($locale) {
            'nl' => 'nl_BE.UTF-8',
            'en' => 'en_US.UTF-8',
            default => 'fr_BE.UTF-8',
        });

        return $next($request);
    }

    protected function resolveLocale(Request $request): string
    {
        $sessionLocale = $request->session()->get('locale');
        if ($this->isSupported($sessionLocale)) {
            return $sessionLocale;
        }

        $userLocale = Auth::user()?->locale;
        $normalizedUserLocale = $this->normalizeLocale($userLocale);
        if ($this->isSupported($normalizedUserLocale)) {
            $request->session()->put('locale', $normalizedUserLocale);
            return $normalizedUserLocale;
        }
        return 'fr';
    }

    protected function normalizeLocale(?string $locale): ?string
    {
        if (! $locale) {
            return null;
        }

        $locale = strtolower(str_replace('-', '_', $locale));

        if (str_starts_with($locale, 'fr')) {
            return 'fr';
        }

        if (str_starts_with($locale, 'nl')) {
            return 'nl';
        }

        if (str_starts_with($locale, 'en')) {
            return 'en';
        }

        return null;
    }

    protected function isSupported(?string $locale): bool
    {
        return in_array($locale, $this->supported, true);
    }
}
