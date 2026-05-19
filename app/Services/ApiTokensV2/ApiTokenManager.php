<?php

namespace App\Services\ApiTokensV2;

use App\Models\Sanctum\PersonalAccessTokenV2;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

class ApiTokenManager
{
    public function __construct(protected ScopeRegistry $scopes) {}

    /**
     * Issue a new token. Returns the wrapper with plain text token (single shot).
     *
     * @param array{
     *   name: string,
     *   display_name?: ?string,
     *   description?: ?string,
     *   scopes?: string[],
     *   owner_role?: ?string,
     *   rate_limit_per_minute?: ?int,
     *   expires_in_days?: ?int,
     * } $payload
     */
    public function createForUser(User $user, array $payload): NewAccessToken
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => ['Le nom du token est requis.']]);
        }
        $role = (string) ($payload['owner_role'] ?? config('api_tokens_v2.default_owner_role', 'api_partner'));
        if (! in_array($role, (array) config('api_tokens_v2.owner_roles', []), true)) {
            throw ValidationException::withMessages(['owner_role' => ['Rôle owner invalide.']]);
        }
        $requested = array_values((array) ($payload['scopes'] ?? []));
        $filtered = $this->scopes->filterForRole($requested, $role);
        if (! empty($filtered['invalid'])) {
            throw ValidationException::withMessages([
                'scopes' => ['Scopes invalides ou non autorisés pour ce rôle : ' . implode(', ', $filtered['invalid'])],
            ]);
        }
        $abilities = $filtered['valid'] ?: ['*'];

        $expiresInDays = $payload['expires_in_days'] ?? (int) config('api_tokens_v2.default_expiry_days', 365);
        $expiresAt = $expiresInDays > 0 ? now()->addDays((int) $expiresInDays) : null;

        $rate = $payload['rate_limit_per_minute'] ?? null;

        /** @var NewAccessToken $new */
        $new = $user->createToken($name, $abilities, $expiresAt);
        $token = $new->accessToken;

        // Cast to V2 instance, set extra fields, save.
        $token->forceFill(array_filter([
            'display_name' => $payload['display_name'] ?? null,
            'description' => $payload['description'] ?? null,
            'owner_role' => $role,
            'rate_limit_per_minute' => $rate,
        ], fn ($v) => $v !== null))->save();

        return $new;
    }

    /**
     * Rotate a token : issue a new one with same scopes/owner_role/expiry,
     * keep old token valid during rotation grace period.
     */
    public function rotate(PersonalAccessTokenV2 $token, ?int $graceHours = null): NewAccessToken
    {
        $graceHours ??= (int) config('api_tokens_v2.rotation_grace_hours', 24);
        $owner = $token->tokenable;
        if (! $owner instanceof User) {
            throw ValidationException::withMessages(['token' => ['Token sans propriétaire utilisateur.']]);
        }
        $abilities = (array) ($token->abilities ?: ['*']);

        $expiresAt = $token->expires_at;
        if ($expiresAt && $expiresAt->isPast()) {
            $expiresAt = now()->addDays((int) config('api_tokens_v2.default_expiry_days', 365));
        }

        $new = $owner->createToken($token->name . ' (rotated)', $abilities, $expiresAt);
        $newToken = $new->accessToken;
        $newToken->forceFill(array_filter([
            'display_name' => $token->display_name,
            'description' => $token->description,
            'owner_role' => $token->owner_role,
            'rate_limit_per_minute' => $token->rate_limit_per_minute,
            'rotated_from_token_id' => $token->id,
            'rotated_at' => now(),
        ], fn ($v) => $v !== null))->save();

        $token->update([
            'rotation_grace_until' => $graceHours > 0 ? now()->addHours($graceHours) : now(),
        ]);

        return $new;
    }

    public function revoke(PersonalAccessTokenV2 $token): void
    {
        DB::transaction(function () use ($token) {
            $token->delete();
        });
    }

    public function suspend(PersonalAccessTokenV2 $token, string $reason): PersonalAccessTokenV2
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => ['Raison minimum 5 caractères.']]);
        }
        $token->update([
            'suspended_at' => now(),
            'suspended_reason' => $reason,
        ]);
        return $token->fresh();
    }

    public function unsuspend(PersonalAccessTokenV2 $token): PersonalAccessTokenV2
    {
        $token->update([
            'suspended_at' => null,
            'suspended_reason' => null,
        ]);
        return $token->fresh();
    }
}
