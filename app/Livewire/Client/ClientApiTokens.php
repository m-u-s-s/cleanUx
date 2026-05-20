<?php

namespace App\Livewire\Client;

use App\Models\ApiTokenScope;
use App\Models\Sanctum\PersonalAccessTokenV2;
use App\Services\ApiTokensV2\ApiTokenManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ClientApiTokens extends Component
{
    public string $newName = '';
    public string $newDescription = '';
    public array $newScopes = [];
    public ?int $newExpiryDays = 365;
    public ?int $newRateLimit = null;

    /** Plain text token affiché APRÈS création — single shot. */
    public ?string $justCreatedToken = null;
    public ?array $justCreatedMeta = null;
    /** Plain text rotated token affiché APRÈS rotation. */
    public ?string $justRotatedToken = null;
    public ?array $justRotatedMeta = null;

    public function rules(): array
    {
        return [
            'newName' => 'required|string|max:191',
            'newDescription' => 'nullable|string|max:2000',
            'newScopes' => 'required|array|min:1',
            'newScopes.*' => 'string|max:64',
            'newExpiryDays' => 'nullable|integer|min:1|max:3650',
            'newRateLimit' => 'nullable|integer|min:1|max:10000',
        ];
    }

    public function createToken(): void
    {
        $this->validate();
        try {
            $new = app(ApiTokenManager::class)->createForUser(Auth::user(), [
                'name' => $this->newName,
                'description' => $this->newDescription ?: null,
                'scopes' => array_values($this->newScopes),
                'owner_role' => 'api_partner',
                'expires_in_days' => $this->newExpiryDays,
                'rate_limit_per_minute' => $this->newRateLimit,
            ]);

            $this->justCreatedToken = $new->plainTextToken;
            $this->justCreatedMeta = [
                'id' => $new->accessToken->id,
                'name' => $new->accessToken->name,
                'expires_at' => optional($new->accessToken->expires_at)?->toIso8601String(),
            ];

            // Reset form
            $this->newName = '';
            $this->newDescription = '';
            $this->newScopes = [];
            $this->newRateLimit = null;

            $this->dispatch('toast', 'Token créé. Copiez-le maintenant — il ne sera plus jamais affiché.', 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', 'Erreur : ' . implode(' / ', collect($e->errors())->flatten()->all()), 'error');
        }
    }

    public function rotate(int $tokenId): void
    {
        $token = PersonalAccessTokenV2::query()
            ->where('id', $tokenId)
            ->where('tokenable_id', Auth::id())
            ->where('tokenable_type', \App\Models\User::class)
            ->first();
        if (! $token) {
            $this->dispatch('toast', 'Token introuvable.', 'error');
            return;
        }
        try {
            $new = app(ApiTokenManager::class)->rotate($token);
            $this->justRotatedToken = $new->plainTextToken;
            $this->justRotatedMeta = [
                'id' => $new->accessToken->id,
                'previous_id' => $token->id,
                'grace_until' => optional($token->fresh()->rotation_grace_until)?->toIso8601String(),
            ];
            $this->dispatch('toast', 'Rotation effectuée. Anciennes credentials valides pendant grace period.', 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', 'Erreur : ' . implode(' / ', collect($e->errors())->flatten()->all()), 'error');
        }
    }

    public function revoke(int $tokenId): void
    {
        $token = PersonalAccessTokenV2::query()
            ->where('id', $tokenId)
            ->where('tokenable_id', Auth::id())
            ->where('tokenable_type', \App\Models\User::class)
            ->first();
        if (! $token) {
            return;
        }
        app(ApiTokenManager::class)->revoke($token);
        $this->dispatch('toast', 'Token révoqué.', 'success');
    }

    public function dismissNewToken(): void
    {
        $this->justCreatedToken = null;
        $this->justCreatedMeta = null;
    }

    public function dismissRotatedToken(): void
    {
        $this->justRotatedToken = null;
        $this->justRotatedMeta = null;
    }

    #[Computed]
    public function myTokens()
    {
        return PersonalAccessTokenV2::query()
            ->where('tokenable_id', Auth::id())
            ->where('tokenable_type', \App\Models\User::class)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function availableScopes()
    {
        return ApiTokenScope::query()
            ->active()
            ->forRole('api_partner')
            ->orderBy('category')
            ->orderBy('code')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.client.client-api-tokens');
    }
}
