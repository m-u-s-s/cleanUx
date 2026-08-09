<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\Catalog\RegistrationOptionsController;
use App\Http\Controllers\Api\Client\GdprController;
use App\Http\Controllers\Api\LocaleListController;
use App\Http\Controllers\Api\PricingV2Controller;
use App\Http\Controllers\Api\Public\FxController;
use App\Http\Controllers\Api\Public\ProviderProfileController;
use App\Http\Controllers\Api\Public\SearchController;
use App\Models\Trade;
use App\Services\Country\CountryConfigService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Public — Health check
// ─────────────────────────────────────────────

Route::get('/health', function () {
    $checks = [
        'app' => true,
        'database' => false,
        'cache' => false,
        'queue' => false,
        'redis' => false,
        'stripe' => null, // null = soft-fail (not counted as degraded)
        'reverb' => null, // null = soft-fail (not counted as degraded)
    ];

    try {
        DB::select('SELECT 1');
        $checks['database'] = true;
    } catch (Throwable $e) {
    }

    try {
        Cache::put('health_check', true, 10);
        $checks['cache'] = Cache::get('health_check') === true;
    } catch (Throwable $e) {
    }

    try {
        $checks['queue'] = config('queue.default') !== 'sync';
    } catch (Throwable $e) {
    }

    // Redis connectivity — required, blocks healthy status if down
    try {
        Redis::ping();
        $checks['redis'] = true;
    } catch (Throwable $e) {
        $checks['redis'] = false;
    }

    // Stripe — soft-fail: checks only that secret is configured, not external reachability
    try {
        $checks['stripe'] = ! empty(config('services.stripe.secret'));
    } catch (Throwable $e) {
        $checks['stripe'] = false;
    }

    // Reverb — soft-fail: checks only that key is configured
    try {
        $checks['reverb'] = ! empty(config('broadcasting.connections.reverb.key'));
    } catch (Throwable $e) {
        $checks['reverb'] = false;
    }

    // Only non-null checks (hard dependencies) affect healthy status
    $hardChecks = array_filter($checks, fn ($v) => $v !== null);
    $healthy = ! in_array(false, $hardChecks, true);

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
        ['q' => 'Comment devenir prestataire ?',            'a' => "Téléchargez l'app Brio Provider, inscrivez-vous et complétez la vérification KYC."],
        ['q' => 'Mes données sont-elles protégées ?',       'a' => 'Oui, Brio est conforme au RGPD. Vous pouvez exporter ou supprimer vos données depuis Profil > RGPD.'],
        ['q' => 'Comment fonctionne le programme fidélité ?', 'a' => 'Cumulez des points à chaque réservation. Montez de niveau (Bronze → Platinum) et échangez vos points contre des réductions.'],
    ]]);
})->name('api.support.faq');

// ─────────────────────────────────────────────
// Public — Country config (cached 1h)
// ─────────────────────────────────────────────

Route::get('/countries', function () {
    $service = app(CountryConfigService::class);

    return response()->json(['data' => $service->all()]);
})->middleware('cache.headers:public;max_age=3600;etag')->name('api.countries');

// ─────────────────────────────────────────────
// Public — Provider profiles + ratings (no auth)
// ─────────────────────────────────────────────

Route::get('/providers/{provider}', [ProviderProfileController::class, 'show']);
Route::get('/providers/{provider}/ratings', [ProviderProfileController::class, 'ratings']);

// Phase i18n v2 — Liste des locales supportées (public, pour pickers UI)
Route::get('/locales', [LocaleListController::class, 'index']);

// Phase Search v2 — Recherche publique providers/services/adresses (rate-limited: 30/min per IP)
Route::middleware('throttle:public-api')->group(function () {
    Route::get('/search/providers', [SearchController::class, 'providers'])->middleware('cache.api:60');
    Route::get('/search/services', [SearchController::class, 'services'])->middleware('cache.api:300');
    Route::get('/search/postal-autocomplete', [SearchController::class, 'postalAutocomplete']);
});

// Phase Analytics v2 — Ingestion publique (auth optionnelle via header bearer)
Route::post('/analytics/track', [AnalyticsController::class, 'track']);
Route::post('/analytics/page', [AnalyticsController::class, 'page']);

// Phase FX v2 — Devises supportées + taux + conversion (public, rate-limited)
Route::middleware('throttle:public-api')->group(function () {
    Route::get('/fx/currencies', [FxController::class, 'currencies']);
    Route::get('/fx/rates', [FxController::class, 'rates']);
    Route::post('/fx/convert', [FxController::class, 'convert']);
});

// Phase Pricing v2 — Service catalog + quote engine (public read + preview, rate-limited)
Route::middleware('throttle:public-api')->group(function () {
    Route::get('/v2/pricing/services', [PricingV2Controller::class, 'services'])->middleware('cache.api:300');
    Route::post('/v2/pricing/preview', [PricingV2Controller::class, 'preview']);
    Route::post('/v2/pricing/quote', [PricingV2Controller::class, 'quote']);
});

// Phase GDPR v2 — Download d'export via URL signée
Route::middleware(['signed', 'auth:sanctum'])
    ->get('/client/gdpr/requests/{gdprRequest}/download',
        [GdprController::class, 'downloadExport'])
    ->name('api.gdpr.download');

/*
 * LES MÉTIERS ET LES ZONES OFFERTS À L'INSCRIPTION — une seule API, web et mobile.
 *
 * Le formulaire web et l'onboarding natif construisaient deux listes différentes : l'un lisait
 * `trades` sans filtre, l'autre ne proposait aucune zone. Un métier ouvert par l'administration
 * apparaissait d'un côté et pas de l'autre, et personne ne savait lequel disait vrai.
 *
 * PUBLIQUE À DESSEIN : appelée AVANT que le compte existe, donc avant tout jeton.
 */
Route::get('/catalog/registration-options', RegistrationOptionsController::class)
    ->name('api.catalog.registration-options');

// Multi-trade marketplace — public trades listing (no auth required)
// GET /api/trades — returns all active trades ordered by sort_order
Route::get('/trades', function () {
    $trades = Trade::where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get(['id', 'name', 'slug', 'code', 'icon', 'description', 'billing_unit', 'requires_site_visit', 'short_description'])
        ->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'code' => $t->code,
            'icon' => $t->icon,
            'description' => $t->short_description ?? $t->description,
            'billing_unit' => $t->billing_unit,
            'requires_site_visit' => (bool) ($t->requires_site_visit ?? false),
        ]);

    return response()->json(['data' => $trades]);
})->middleware(['cache.headers:public;max_age=300;etag', 'cache.api:300'])->name('api.trades.index');

// Questions posées au PRESTATAIRE qui déclare exercer ce métier (trades.provider_form_schema).
// GET /api/trades/{trade}/provider-fields
//
// Public à dessein : le formulaire d'inscription prestataire les affiche avant que le compte
// n'existe. L'équivalent client (/api/client/trades/{trade}/form-fields) est authentifié et décrit
// d'autres questions — celles posées au client qui réserve.
Route::get('/trades/{trade}/provider-fields', function (Trade $trade) {
    return response()->json([
        'trade' => [
            'id' => $trade->id,
            'name' => $trade->name,
            'slug' => $trade->slug,
            'requires_certification' => (bool) $trade->requires_certification,
            'requires_insurance_proof' => (bool) $trade->requires_insurance_proof,
        ],
        // Un métier sans schéma rend une liste vide plutôt qu'une erreur : le formulaire reste
        // utilisable, il ne pose simplement aucune question spécifique.
        'fields' => data_get($trade->provider_form_schema, 'fields', []),
    ]);
})->middleware(['cache.headers:public;max_age=300;etag', 'cache.api:300'])->name('api.trades.provider-fields');
