<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase 9 — Switch de langue persisté.
 *
 * Endpoint : POST /locale  (CSRF-protected)
 *   - Met la locale en session
 *   - Persiste sur le user si connecté
 *   - Redirige back avec cookie 1 an
 */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locale = (string) $request->input('locale', '');

        if (! in_array($locale, SetLocale::SUPPORTED, true)) {
            return back()->with('error', __('messages.invalid_locale'));
        }

        // Session
        $request->session()->put('locale', $locale);

        // User
        $user = $request->user();
        if ($user && $user->locale !== $locale) {
            $user->locale = $locale;
            $user->save();
        }

        return back()
            ->with('success', __('messages.language_changed'))
            ->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }
}
