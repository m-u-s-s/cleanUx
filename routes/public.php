<?php

use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\PremiumCheckoutController;
use App\Http\Controllers\PricingPageController;
use App\Http\Controllers\PublicSeoController;
use App\Http\Controllers\ServicePageController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\Webhooks\GoogleCalendarWebhookController;
use App\Http\Controllers\Webhooks\InsuranceWebhookController;
use App\Http\Controllers\Webhooks\KycWebhookController;
use App\Http\Controllers\Webhooks\SmsWebhookController;
use App\Http\Controllers\Webhooks\StripeConnectWebhookController;
use App\Livewire\Client\BrowseProviders;
use App\Livewire\Client\PremiumOfferPage;
use App\Livewire\Client\PrendreRendezVous;
use App\Livewire\Public\HelpCenter;
use App\Livewire\Public\ProviderPublicProfile;
use App\Models\Country;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

// Premium scroll engine — vitrine interactive des 6 features (smooth, pin,
// parallax, progress, horizontal, responsive). Disponible en local/staging
// uniquement : ne ship jamais en production, jamais indexée.
if (! app()->isProduction()) {
    Route::view('/premium-scroll', 'premium-scroll-demo')->name('premium-scroll.demo');

    // Étude de design éditorial premium (marque FICTIVE « Northlight », contenu
    // ORIGINAL) : démontre espacement, grille, rythme typo, mouvement,
    // interaction et hiérarchie. Local/staging only, jamais indexée.
    Route::view('/editorial', 'pages.editorial-study')->name('editorial.study');

    // Landing luxe SOMBRE (marque FICTIVE « Obsidia », contenu ORIGINAL) : hero
    // WebGL R3F + architecture 9 sections, réutilise tous les moteurs (thème
    // .ed--dark). Local/staging only, jamais indexée.
    Route::view('/luxe', 'pages.luxe')->name('luxe.landing');
}

Route::get('/pricing', PricingPageController::class)->name('pricing');

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
                $userLocale = $locale.'_'.$region;
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

if (class_exists(ProviderPublicProfile::class)) {
    Route::get('/providers/{provider}', ProviderPublicProfile::class)
        ->name('providers.show');
}

if (class_exists(BrowseProviders::class)) {
    Route::get('/prestataires', BrowseProviders::class)
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
Route::get('/health', [HealthCheckController::class, 'liveness'])->name('health.liveness');
Route::get('/health/deep', [HealthCheckController::class, 'readiness'])->name('health.readiness');

// Help Center / FAQ
if (class_exists(HelpCenter::class)) {
    Route::get('/aide', HelpCenter::class)->name('help.center');
}

// SEO endpoints (sitemap.xml + robots.txt)
Route::get('/sitemap.xml', [PublicSeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [PublicSeoController::class, 'robots'])->name('seo.robots');

// Blog — content marketing foundation (no auth required, crawlable)
Route::get('/blog', function () {
    return view('pages.blog-index', [
        'seoTitle' => 'Blog — CleanUx | Conseils, actualités, guides',
        'seoDescription' => 'Découvrez nos articles sur les services à domicile, conseils nettoyage, guides travaux, et actualités CleanUx.',
    ]);
})->name('blog.index');

// Programmatic SEO — service pages (no auth required, crawlable)
Route::get('/services', [ServicePageController::class, 'index'])->name('services.index');
Route::get('/services/{trade}', [ServicePageController::class, 'show'])->name('services.show');
Route::get('/services/{trade}/{city}', [ServicePageController::class, 'show'])->name('services.show.city');

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
Route::post('/webhooks/kyc/{provider}', [KycWebhookController::class, 'handle'])
    ->name('webhooks.kyc');

// Phase SMS v2 — Webhooks DLR SMS providers (mock|twilio|vonage)
Route::post('/webhooks/sms/{provider}', [SmsWebhookController::class, 'handle'])
    ->name('webhooks.sms');

// Phase Insurance v2 — Webhooks providers assurance (mock|hiscox|wakam)
Route::post('/webhooks/insurance/{provider}', [InsuranceWebhookController::class, 'handle'])
    ->name('webhooks.insurance');

// GCal bidirectionnel — notifications push Google Calendar (headers X-Goog-*).
Route::post('/webhooks/google-calendar', [GoogleCalendarWebhookController::class, 'handle'])
    ->name('webhooks.google-calendar');
