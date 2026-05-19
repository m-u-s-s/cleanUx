<?php

namespace App\Http\Controllers;

use App\Services\I18n\LocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase 9+i18n v2 — Switch de langue persisté.
 *
 * POST /locale  (CSRF-protected)
 *   - Met la locale en session
 *   - Persiste sur le user (au format BCP47 stocké : ex 'nl_BE') si connecté
 *   - Redirige back avec cookie 1 an
 */
class LocaleController extends Controller
{
    public function __construct(protected LocaleResolver $resolver)
    {
    }

    public function update(Request $request): RedirectResponse
    {
        $locale = (string) $request->input('locale', '');

        if (! $this->resolver->isSupported($locale)) {
            return back()->with('error', __('messages.invalid_locale'));
        }

        $request->session()->put('locale', $locale);

        $user = $request->user();
        if ($user) {
            $user->forceFill([
                'locale' => $this->resolver->userPersistedFormat($locale),
            ])->save();
        }

        return back()
            ->with('success', __('messages.language_changed'))
            ->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }
}
