<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Promotion\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Phase 12 — Authentification API mobile (Sanctum tokens).
 *
 * Endpoints :
 *   POST /api/auth/login    → token + user
 *   POST /api/auth/register → token + user (clients seulement)
 *   POST /api/auth/logout   → révoque le token courant
 *
 * Sécurité :
 *   - Rate limit 5 tentatives/min par IP+email sur login
 *   - Email vérification optionnelle (selon ta config Fortify)
 *   - Le token retourné est un PersonalAccessToken Sanctum, durée illimitée
 *     par défaut (configurable via config/sanctum.php)
 */
/**
 * @group Authentication
 */
class ApiAuthController extends Controller
{
    /**
     * Authenticate a user and return a Sanctum token.
     *
     * @bodyParam email string required The user's email address. Example: alice@example.com
     * @bodyParam password string required The user's password (min 6 chars). Example: secret123
     * @bodyParam device_name string Optional device identifier stored with the token. Example: iPhone 15
     *
     * @response 200 {"ok": true, "token": "1|abc123def456...", "user": {"id": 1, "name": "Alice Dupont", "email": "alice@example.com", "phone": "+32471000001", "role": "client", "platform_role": "user", "locale": "fr", "is_provider": false, "is_admin": false, "organization_account_id": null}}
     * @response 422 {"message": "Identifiants incorrects.", "errors": {"email": ["Identifiants incorrects."]}}
     * @response 429 {"message": "Trop de tentatives. Réessaie dans 42 secondes.", "errors": {"email": ["Trop de tentatives. Réessaie dans 42 secondes."]}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Rate limit : 5 tentatives par minute par email+IP
        $key = 'api-login:'.strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Trop de tentatives. Réessaie dans {$seconds} secondes.",
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        // Reset rate limit après login réussi
        RateLimiter::clear($key);

        $deviceName = $data['device_name'] ?? $request->userAgent() ?? 'mobile';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'ok' => true,
            'token' => $token,
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * Register a new client account and return a Sanctum token.
     *
     * @bodyParam name string required Full display name. Example: Alice Dupont
     * @bodyParam email string required Unique email address. Example: alice@example.com
     * @bodyParam password string required Minimum 8 characters. Example: s3cur3pass!
     * @bodyParam password_confirmation string required Must match password. Example: s3cur3pass!
     * @bodyParam phone string Optional phone number. Example: +32471000001
     * @bodyParam locale string Optional UI locale (fr, nl, en). Example: fr
     * @bodyParam accept_terms boolean required Must be accepted (1/true). Example: 1
     * @bodyParam device_name string Optional device identifier. Example: Android Pixel 8
     * @bodyParam referral_code string Optional referral code from an existing user. Example: REF-ABCD1234
     *
     * @response 201 {"ok": true, "token": "2|xyz789...", "user": {"id": 42, "name": "Alice Dupont", "email": "alice@example.com", "phone": "+32471000001", "role": "client", "platform_role": "user", "locale": "fr", "is_provider": false, "is_admin": false, "organization_account_id": null}}
     * @response 422 {"message": "The email has already been taken.", "errors": {"email": ["The email has already been taken."]}}
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Crée un user de type "client particulier" (cas mobile le plus simple)
        // Pour devenir prestataire, parcours d'onboarding séparé (Phase 13+)
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'locale' => $data['locale'] ?? 'fr',
            'platform_role' => User::PLATFORM_USER,
            'role' => 'client',
        ]);

        // Apply referral code if provided — soft-fail, never blocks registration
        if (! empty($data['referral_code']) && config('referral.enabled', true)) {
            try {
                $sourceChannel = $request->header('X-Source-Channel', 'api_register');
                app(ReferralService::class)->registerReferral(
                    $data['referral_code'],
                    $user,
                    $sourceChannel,
                    $request->ip(),
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $deviceName = $data['device_name'] ?? $request->userAgent() ?? 'mobile';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'ok' => true,
            'token' => $token,
            'user' => $this->serializeUser($user),
        ], 201);
    }

    /**
     * Revoke the current access token (logout from this device only).
     *
     * @response 200 {"ok": true}
     */
    public function logout(Request $request): JsonResponse
    {
        // Révoque le token courant uniquement (pas tous les devices)
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Revoke all access tokens for the authenticated user (logout from all devices).
     *
     * @response 200 {"ok": true, "revoked_all": true}
     */
    public function logoutAll(Request $request): JsonResponse
    {
        // Révoque TOUS les tokens (utile pour "déconnecte tous mes appareils")
        $request->user()->tokens()->delete();

        return response()->json(['ok' => true, 'revoked_all' => true]);
    }

    /**
     * Serializer minimal utilisé par login + register pour réponse cohérente.
     */
    protected function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? null,
            'platform_role' => $user->platform_role ?? null,
            'locale' => $user->locale ?? 'fr',
            'is_provider' => method_exists($user, 'isProvider') && $user->isProvider(),
            'is_admin' => method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin(),
            'is_client' => method_exists($user, 'isClient') && $user->isClient(),
            'is_entreprise' => method_exists($user, 'isEntreprise') && $user->isEntreprise(),
            'organization_account_id' => $user->organization_account_id ?? $user->current_organization_id ?? null,
        ];
    }
}
