<?php

use App\Models\Trade;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Public — Health check
// ─────────────────────────────────────────────

Route::get('/health', function () {
    $checks = [
        'app'      => true,
        'database' => false,
        'cache'    => false,
        'queue'    => false,
        'redis'    => false,
        'stripe'   => null, // null = soft-fail (not counted as degraded)
        'reverb'   => null, // null = soft-fail (not counted as degraded)
    ];

    try {
        \Illuminate\Support\Facades\DB::select('SELECT 1');
        $checks['database'] = true;
    } catch (\Throwable $e) {}

    try {
        \Illuminate\Support\Facades\Cache::put('health_check', true, 10);
        $checks['cache'] = \Illuminate\Support\Facades\Cache::get('health_check') === true;
    } catch (\Throwable $e) {}

    try {
        $checks['queue'] = config('queue.default') !== 'sync';
    } catch (\Throwable $e) {}

    // Redis connectivity — required, blocks healthy status if down
    try {
        \Illuminate\Support\Facades\Redis::ping();
        $checks['redis'] = true;
    } catch (\Throwable $e) {
        $checks['redis'] = false;
    }

    // Stripe — soft-fail: checks only that secret is configured, not external reachability
    try {
        $checks['stripe'] = ! empty(config('services.stripe.secret'));
    } catch (\Throwable $e) {
        $checks['stripe'] = false;
    }

    // Reverb — soft-fail: checks only that key is configured
    try {
        $checks['reverb'] = ! empty(config('broadcasting.connections.reverb.key'));
    } catch (\Throwable $e) {
        $checks['reverb'] = false;
    }

    // Only non-null checks (hard dependencies) affect healthy status
    $hardChecks = array_filter($checks, fn ($v) => $v !== null);
    $healthy    = ! in_array(false, $hardChecks, true);

    return response()->json(
        ['status' => $healthy ? 'healthy' : 'degraded', 'checks' => $checks],
        $healthy ? 200 : 503
    );
})->name('api.health');

// ─────────────────────────────────────────────
// Public — Support FAQ
// ─────────────────────────────────────────────

Route::get('/support/faq', function () {
    return response()->json(['data' => [
        ['q' => 'Comment annuler une réservation ?',        'a' => "Allez dans Mes réservations, sélectionnez la réservation et appuyez sur Annuler. Des frais peuvent s'appliquer selon le délai."],
        ['q' => 'Comment contacter mon prestataire ?',      'a' => 'Ouvrez le détail de votre réservation et utilisez la messagerie intégrée.'],
        ['q' => 'Comment ajouter un moyen de paiement ?',   'a' => 'Allez dans Profil > Mes moyens de paiement > Ajouter une carte.'],
        ['q' => 'Comment devenir prestataire ?',            'a' => "Téléchargez l'app CleanUx Provider, inscrivez-vous et complétez la vérification KYC."],
        ['q' => 'Mes données sont-elles protégées ?',       'a' => 'Oui, CleanUx est conforme au RGPD. Vous pouvez exporter ou supprimer vos données depuis Profil > RGPD.'],
        ['q' => 'Comment fonctionne le programme fidélité ?', 'a' => 'Cumulez des points à chaque réservation. Montez de niveau (Bronze → Platinum) et échangez vos points contre des réductions.'],
    ]]);
})->name('api.support.faq');

// ─────────────────────────────────────────────
// Public — Country config (cached 1h)
// ─────────────────────────────────────────────

Route::get('/countries', function () {
    $service = app(\App\Services\Country\CountryConfigService::class);
    return response()->json(['data' => $service->all()]);
})->middleware('cache.headers:public;max_age=3600;etag')->name('api.countries');

// ─────────────────────────────────────────────
// Public — Provider profiles + ratings (no auth)
// ─────────────────────────────────────────────

Route::get('/providers/{provider}',         [\App\Http\Controllers\Api\Public\ProviderProfileController::class, 'show']);
Route::get('/providers/{provider}/ratings', [\App\Http\Controllers\Api\Public\ProviderProfileController::class, 'ratings']);

// Phase i18n v2 — Liste des locales supportées (public, pour pickers UI)
Route::get('/locales', [\App\Http\Controllers\Api\LocaleListController::class, 'index']);

// Phase Search v2 — Recherche publique providers/services/adresses (rate-limited: 30/min per IP)
Route::middleware('throttle:public-api')->group(function () {
    Route::get('/search/providers',           [\App\Http\Controllers\Api\Public\SearchController::class, 'providers'])->middleware('cache.api:60');
    Route::get('/search/services',            [\App\Http\Controllers\Api\Public\SearchController::class, 'services'])->middleware('cache.api:300');
    Route::get('/search/postal-autocomplete', [\App\Http\Controllers\Api\Public\SearchController::class, 'postalAutocomplete']);
});

// Phase Analytics v2 — Ingestion publique (auth optionnelle via header bearer)
Route::post('/analytics/track', [\App\Http\Controllers\Api\AnalyticsController::class, 'track']);
Route::post('/analytics/page',  [\App\Http\Controllers\Api\AnalyticsController::class, 'page']);

// Phase FX v2 — Devises supportées + taux + conversion (public, rate-limited)
Route::middleware('throttle:public-api')->group(function () {
    Route::get('/fx/currencies', [\App\Http\Controllers\Api\Public\FxController::class, 'currencies']);
    Route::get('/fx/rates',      [\App\Http\Controllers\Api\Public\FxController::class, 'rates']);
    Route::post('/fx/convert',   [\App\Http\Controllers\Api\Public\FxController::class, 'convert']);
});

// Phase Pricing v2 — Service catalog + quote engine (public read + preview, rate-limited)
Route::middleware('throttle:public-api')->group(function () {
    Route::get('/v2/pricing/services', [\App\Http\Controllers\Api\PricingV2Controller::class, 'services'])->middleware('cache.api:300');
    Route::post('/v2/pricing/preview', [\App\Http\Controllers\Api\PricingV2Controller::class, 'preview']);
    Route::post('/v2/pricing/quote',   [\App\Http\Controllers\Api\PricingV2Controller::class, 'quote']);
});

// Phase GDPR v2 — Download d'export via URL signée
Route::middleware(['signed', 'auth:sanctum'])
    ->get('/client/gdpr/requests/{gdprRequest}/download',
        [\App\Http\Controllers\Api\Client\GdprController::class, 'downloadExport'])
    ->name('api.gdpr.download');

// Multi-trade marketplace — public trades listing (no auth required)
// GET /api/trades — returns all active trades ordered by sort_order
Route::get('/trades', function () {
    $trades = Trade::where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get(['id', 'name', 'slug', 'code', 'icon', 'description', 'billing_unit', 'requires_site_visit', 'short_description'])
        ->map(fn ($t) => [
            'id'               => $t->id,
            'name'             => $t->name,
            'slug'             => $t->slug,
            'code'             => $t->code,
            'icon'             => $t->icon,
            'description'      => $t->short_description ?? $t->description,
            'billing_unit'     => $t->billing_unit,
            'requires_site_visit' => (bool) ($t->requires_site_visit ?? false),
        ]);

    return response()->json(['data' => $trades]);
})->middleware('cache.headers:public;max_age=300;etag')->name('api.trades.index');
