<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
class ApiAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'        => ['required', 'email', 'max:255'],
            'password'     => ['required', 'string', 'min:6'],
            'device_name'  => ['nullable', 'string', 'max:100'],
        ]);

        // Rate limit : 5 tentatives par minute par email+IP
        $key = 'api-login:' . strtolower($data['email']) . '|' . $request->ip();
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
            'ok'    => true,
            'token' => $token,
            'user'  => $this->serializeUser($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'          => ['required', 'string', 'min:8', 'confirmed'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'locale'            => ['nullable', 'string', 'in:fr,nl,en'],
            'accept_terms'      => ['required', 'accepted'],
            'device_name'       => ['nullable', 'string', 'max:100'],
        ]);

        // Crée un user de type "client particulier" (cas mobile le plus simple)
        // Pour devenir prestataire, parcours d'onboarding séparé (Phase 13+)
        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'phone'         => $data['phone'] ?? null,
            'locale'        => $data['locale'] ?? 'fr',
            'platform_role' => User::PLATFORM_USER,
            'role'          => 'client',
        ]);

        $deviceName = $data['device_name'] ?? $request->userAgent() ?? 'mobile';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'ok'    => true,
            'token' => $token,
            'user'  => $this->serializeUser($user),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        // Révoque le token courant uniquement (pas tous les devices)
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['ok' => true]);
    }

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
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'phone'          => $user->phone ?? null,
            'role'           => $user->role ?? null,
            'platform_role'  => $user->platform_role ?? null,
            'locale'         => $user->locale ?? 'fr',
            'is_provider'    => method_exists($user, 'isProvider') && $user->isProvider(),
            'is_admin'       => method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin(),
            'organization_account_id' => $user->organization_account_id ?? $user->current_organization_id ?? null,
        ];
    }
}
