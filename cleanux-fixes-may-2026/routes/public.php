<?php

use App\Http\Controllers\PremiumCheckoutController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\Webhooks\StripeConnectWebhookController;
use App\Livewire\Client\PremiumOfferPage;
use App\Livewire\Client\PrendreRendezVous;
use App\Models\Country;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::post('/locale', function (Request $request) {
    $locale = $request->string('locale')->toString();

    abort_unless(in_array($locale, ['fr', 'nl', 'en'], true), 404);

    session(['locale' => $locale]);

    if (auth()->check()) {
        /** @var User $user */
        $user = auth()->user();

        $user->forceFill([
            'locale' => match ($locale) {
                'nl' => 'nl_BE',
                'en' => 'en_US',
                default => 'fr_BE',
            },
        ])->save();
    }

    return back();
})->name('locale.switch');

Route::post('/country', function (Request $request) {
    $country = Country::query()
        ->where('iso_code', strtoupper($request->string('country')->toString()))
        ->where('is_active', true)
        ->firstOrFail();

    $request->session()->put('country', $country->iso_code);

    if (auth()->check()) {
        /** @var User $user */
        $user = auth()->user();

        $metadata = (array) ($user->metadata ?? []);
        $metadata['current_country_code'] = $country->iso_code;

        $user->forceFill(['metadata' => $metadata])->save();
    }

    return back();
})->name('country.switch');

Route::get('/premium', PremiumOfferPage::class)->name('premium.offer');

Route::middleware(['auth', 'verified', 'active.account'])->group(function () {
    Route::post('/premium/checkout', [PremiumCheckoutController::class, 'checkout'])
        ->name('premium.checkout');

    Route::get('/premium/success', [PremiumCheckoutController::class, 'success'])
        ->name('premium.success');

    Route::get('/premium/cancel', [PremiumCheckoutController::class, 'cancel'])
        ->name('premium.cancel');
});

Route::get('/prendre-rendez-vous', PrendreRendezVous::class)->name('booking.create');

Route::get('/terms-of-service', function () {
    return view()->exists('terms')
        ? view('terms')
        : response('<h1>Conditions générales</h1><p>Page à compléter.</p>');
})->name('terms.show');

Route::get('/privacy-policy', function () {
    return view()->exists('policy')
        ? view('policy')
        : response('<h1>Politique de confidentialité</h1><p>Page à compléter.</p>');
})->name('policy.show');

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');

// ─────────────────────────────────────────────────────────────
// Phase 13 — Webhook Stripe Connect (séparé de Cashier subscription)
// ─────────────────────────────────────────────────────────────
// Vérifie sa propre signature via STRIPE_CONNECT_WEBHOOK_SECRET.
// Doit être listé dans VerifyCsrfToken::$except (déjà fait).
Route::post('/webhooks/stripe-connect', [StripeConnectWebhookController::class, 'handle'])
    ->name('webhooks.stripe-connect');