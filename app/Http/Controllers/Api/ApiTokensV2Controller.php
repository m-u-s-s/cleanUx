<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiTokenScope;
use App\Models\ApiTokenUsage;
use App\Models\Sanctum\PersonalAccessTokenV2;
use App\Services\ApiTokensV2\ApiTokenManager;
use App\Services\ApiTokensV2\ScopeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApiTokensV2Controller extends Controller
{
    public function __construct(
        protected ApiTokenManager $manager,
        protected ScopeRegistry $scopes,
    ) {}

    public function scopesCatalog(): JsonResponse
    {
        $rows = ApiTokenScope::query()
            ->active()
            ->orderBy('category')
            ->orderBy('code')
            ->get(['code', 'name', 'description', 'category', 'required_role', 'is_dangerous']);
        return response()->json(['data' => $rows]);
    }

    public function listMyTokens(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = PersonalAccessTokenV2::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
        return response()->json([
            'data' => $rows->map(fn ($t) => $this->presentToken($t))->all(),
        ]);
    }

    public function createMyToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'display_name' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'max:64'],
            'owner_role' => ['nullable', 'string', 'max:32'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        try {
            $new = $this->manager->createForUser($request->user(), $data);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'ok' => true,
            'plain_text_token' => $new->plainTextToken,
            'token' => $this->presentToken($new->accessToken),
            'note' => 'Conservez plain_text_token — il ne sera plus jamais affiché.',
        ], 201);
    }

    public function rotateMyToken(Request $request, PersonalAccessTokenV2 $token): JsonResponse
    {
        if ($token->tokenable_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $new = $this->manager->rotate($token);
        return response()->json([
            'ok' => true,
            'plain_text_token' => $new->plainTextToken,
            'token' => $this->presentToken($new->accessToken),
            'old_token_grace_until' => $token->fresh()->rotation_grace_until?->toIso8601String(),
        ]);
    }

    public function revokeMyToken(Request $request, PersonalAccessTokenV2 $token): JsonResponse
    {
        if ($token->tokenable_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $this->manager->revoke($token);
        return response()->json(['ok' => true]);
    }

    public function adminListTokens(Request $request): JsonResponse
    {
        $rows = PersonalAccessTokenV2::query()
            ->with(['tokenable'])
            ->when($request->filled('owner_role'), fn ($q) => $q->where('owner_role', $request->string('owner_role')))
            ->when($request->filled('suspended'), function ($q) use ($request) {
                $request->boolean('suspended')
                    ? $q->whereNotNull('suspended_at')
                    : $q->whereNull('suspended_at');
            })
            ->orderByDesc('created_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();
        return response()->json([
            'data' => $rows->map(fn ($t) => $this->presentToken($t, includeOwner: true))->all(),
        ]);
    }

    public function adminListUsages(Request $request): JsonResponse
    {
        $rows = ApiTokenUsage::query()
            ->when($request->filled('token_id'), fn ($q) => $q->where('token_id', $request->integer('token_id')))
            ->when($request->filled('status_min'), fn ($q) => $q->where('response_status', '>=', $request->integer('status_min')))
            ->orderByDesc('occurred_at')
            ->limit((int) $request->integer('limit', 100))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminSuspend(Request $request, PersonalAccessTokenV2 $token): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        try {
            $row = $this->manager->suspend($token, $data['reason']);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'token' => $this->presentToken($row)]);
    }

    public function adminUnsuspend(PersonalAccessTokenV2 $token): JsonResponse
    {
        $row = $this->manager->unsuspend($token);
        return response()->json(['ok' => true, 'token' => $this->presentToken($row)]);
    }

    public function adminRevoke(PersonalAccessTokenV2 $token): JsonResponse
    {
        $this->manager->revoke($token);
        return response()->json(['ok' => true]);
    }

    protected function presentToken(PersonalAccessTokenV2 $t, bool $includeOwner = false): array
    {
        $payload = [
            'id' => $t->id,
            'name' => $t->name,
            'display_name' => $t->display_name,
            'description' => $t->description,
            'owner_role' => $t->owner_role,
            'abilities' => (array) ($t->abilities ?: []),
            'rate_limit_per_minute' => $t->effectiveRateLimit(),
            'is_suspended' => $t->isSuspended(),
            'suspended_reason' => $t->suspended_reason,
            'expires_at' => $t->expires_at?->toIso8601String(),
            'last_used_at' => $t->last_used_at?->toIso8601String(),
            'usage_count' => $t->usage_count,
            'rotated_from_token_id' => $t->rotated_from_token_id,
            'rotation_grace_until' => $t->rotation_grace_until?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
        ];
        if ($includeOwner) {
            $payload['owner'] = [
                'type' => $t->tokenable_type,
                'id' => $t->tokenable_id,
                'email' => optional($t->tokenable)->email ?? null,
            ];
        }
        return $payload;
    }
}
