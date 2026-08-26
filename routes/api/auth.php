<?php

use App\Http\Controllers\Api\Auth\ApiAuthController;
use App\Http\Controllers\Api\Auth\CompanyLookupController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\RegistrationPhoneController;
use App\Http\Controllers\Api\Auth\WebViewAuthController;
use App\Http\Controllers\Api\AuthMeController;
use App\Http\Controllers\Api\AuthRefreshController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Public — Auth (login / register / forgot-password)
// ─────────────────────────────────────────────

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('/login', [ApiAuthController::class, 'login']);
    Route::post('/register', [ApiAuthController::class, 'register'])->middleware('turnstile');
});

// Vérification du téléphone au tout premier écran de l'inscription, donc avant que le compte
// existe : ces deux routes sont nécessairement publiques. Elles déclenchent des SMS payants, d'où
// `throttle:otp` plutôt que `throttle:auth`, en plus des plafonds par numéro de SmsService.
Route::prefix('auth/phone')->middleware('throttle:otp')->group(function () {
    Route::post('/verify-request', [RegistrationPhoneController::class, 'requestCode']);
    Route::post('/verify-confirm', [RegistrationPhoneController::class, 'confirm']);
});

// Recherche d'une entreprise dans les registres officiels pendant l'inscription : le prestataire
// tape son numéro et confirme sa raison sociale au lieu de la recopier. Publique par nécessité —
// le compte n'existe pas encore — donc throttlée, et la clé du numéro est contrôlée avant tout
// appel sortant vers le registre.
Route::post('/auth/company-lookup', CompanyLookupController::class)->middleware('throttle:otp');

// POST /auth/forgot-password — silently ignores unknown emails (mobile app)
Route::post('/auth/forgot-password', ForgotPasswordController::class)->middleware('throttle:5,1');

// ─────────────────────────────────────────────
// Authenticated — Token management + identity
// ─────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'active.account'])->group(function () {

    // Token refresh + grace period + identity
    Route::middleware('token.grace')->group(function () {
        Route::post('/auth/refresh', AuthRefreshController::class)
            ->name('api.auth.refresh');
        Route::get('/auth/me', AuthMeController::class)->name('api.auth.me');
    });

    // Se deconnecter reste permis a un compte suspendu : le refuser laisserait l'application
    // avec un jeton mort qu'elle ne peut pas rendre.
    Route::post('/auth/logout', [ApiAuthController::class, 'logout'])
        ->withoutMiddleware('active.account');
    Route::post('/auth/logout-all', [ApiAuthController::class, 'logoutAll'])
        ->withoutMiddleware('active.account');

    Route::post('/auth/webview-ticket', [WebViewAuthController::class, 'ticket'])
        ->name('api.auth.webview-ticket');
});
