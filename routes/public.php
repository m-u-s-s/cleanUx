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
    $locale = $request->validate([
        'locale' => ['required', 'in:fr,nl,en'],
    ])['locale'];

    session(['locale' => $locale]);
    app()->setLocale($locale);

    if ($request->user()) {
        $current = (string) ($request->user()->locale ?? '');
        $userLocale = $locale;
        if (str_contains($current, '_')) {
            $region = explode('_', $current, 2)[1] ?? '';
            if ($region !== '') {
                $userLocale = $locale . '_' . $region;
            }
        }

        $request->user()->forceFill([
            'locale' => $userLocale,
        ])->save();
    }

    return redirect()->to(route('home'));
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

if (class_exists(\App\Livewire\Public\ProviderPublicProfile::class)) {
    Route::get('/providers/{provider}', \App\Livewire\Public\ProviderPublicProfile::class)
        ->name('providers.show');
}

if (class_exists(\App\Livewire\Client\BrowseProviders::class)) {
    Route::get('/prestataires', \App\Livewire\Client\BrowseProviders::class)
        ->name('providers.browse.public');
}

Route::get('/terms-of-service', function () {
    return view('legal.terms');
})->name('terms.show');

Route::get('/privacy-policy', function () {
    return view('legal.privacy');
})->name('policy.show');

Route::get('/legal/cookies', function () {
    return view('legal.cookies');
})->name('legal.cookies');

Route::get('/legal/mentions-legales', function () {
    return view('legal.mentions');
})->name('legal.mentions');

// Health checks for load balancer / monitoring
Route::get('/health', [\App\Http\Controllers\HealthCheckController::class, 'liveness'])->name('health.liveness');
Route::get('/health/deep', [\App\Http\Controllers\HealthCheckController::class, 'readiness'])->name('health.readiness');

// Help Center / FAQ
if (class_exists(\App\Livewire\Public\HelpCenter::class)) {
    Route::get('/aide', \App\Livewire\Public\HelpCenter::class)->name('help.center');
}

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');

// ─────────────────────────────────────────────────────────────
// Phase 13 — Webhook Stripe Connect (séparé de Cashier subscription)
// ─────────────────────────────────────────────────────────────
// Vérifie sa propre signature via STRIPE_CONNECT_WEBHOOK_SECRET.
// Doit être listé dans VerifyCsrfToken::$except (déjà fait).
Route::post('/webhooks/stripe-connect', [StripeConnectWebhookController::class, 'handle'])
    ->name('webhooks.stripe-connect');

// Phase KYC v2 — Webhooks providers KYC (mock|onfido|veriff|sumsub)
Route::post('/webhooks/kyc/{provider}', [\App\Http\Controllers\Webhooks\KycWebhookController::class, 'handle'])
    ->name('webhooks.kyc');

// Phase SMS v2 — Webhooks DLR SMS providers (mock|twilio|vonage)
Route::post('/webhooks/sms/{provider}', [\App\Http\Controllers\Webhooks\SmsWebhookController::class, 'handle'])
    ->name('webhooks.sms');

// Phase Insurance v2 — Webhooks providers assurance (mock|hiscox|wakam)
Route::post('/webhooks/insurance/{provider}', [\App\Http\Controllers\Webhooks\InsuranceWebhookController::class, 'handle'])
    ->name('webhooks.insurance');
