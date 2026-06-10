<?php

namespace App\Http;

use App\Http\Middleware\ApiTokensV2\EnforceTokenScope;
use App\Http\Middleware\ApiTokensV2\LogTokenUsage;
use App\Http\Middleware\ApiTokensV2\ThrottleByToken;
use App\Http\Middleware\AssistantRateLimit;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CacheApiResponse;
use App\Http\Middleware\CaptureUtm;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EmbedMode;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\Enforce2FA;
use App\Http\Middleware\EnforceTokenGrace;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureFieldTeamLead;
use App\Http\Middleware\EnsureOrganizationType;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\LogSlowQueries;
use App\Http\Middleware\PremiumGate;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustHosts;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateSignature;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Middleware\VerifyTurnstileCaptcha;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        ForceHttps::class,
        TrustHosts::class,
        TrustProxies::class,
        HandleCors::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        SecurityHeaders::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            SetLocale::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            SetLocale::class,
            CaptureUtm::class,
            EmbedMode::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            ThrottleRequests::class.':api',
            SubstituteBindings::class,
            LogSlowQueries::class,
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used instead of class names to conveniently assign middleware to routes and groups.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'auth.session' => AuthenticateSession::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'precognitive' => HandlePrecognitiveRequests::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
        'role' => CheckRole::class,
        'active.account' => EnsureActiveAccount::class,
        'org.type' => EnsureOrganizationType::class,
        'field.team.lead' => EnsureFieldTeamLead::class,
        'assistant.ratelimit' => AssistantRateLimit::class,
        'api_scope' => EnforceTokenScope::class,
        'api_token_throttle' => ThrottleByToken::class,
        'api_token_audit' => LogTokenUsage::class,
        'turnstile' => VerifyTurnstileCaptcha::class,
        'token.grace' => EnforceTokenGrace::class,
        'cache.api' => CacheApiResponse::class,
        'premium' => PremiumGate::class,
        'enforce_2fa' => Enforce2FA::class,
    ];
}
