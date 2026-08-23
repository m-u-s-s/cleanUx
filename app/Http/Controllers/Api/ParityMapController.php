<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/parity-map Returns the modules visible to the authenticated user, each tagged with its mobile delivery mode.
 *
 * @group Parity
 *
 * @authenticated
 */
class ParityMapController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array<int, array<string, mixed>> $rawModules */
        $rawModules = config('parity.modules', []);

        $modules = collect($rawModules)
            ->filter(fn (array $m) => $this->visibleTo($user, $m['roles'] ?? []))
            ->map(fn (array $m) => [
                'key' => $m['key'],
                'title' => $m['title'],
                'icon' => $m['icon'],
                'path' => $m['path'],
                'mobile' => $m['mobile'],
            ])
            ->values();

        return response()->json(['data' => $modules]);
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function visibleTo(User $user, array $roles): bool
    {
        if ($roles === []) {
            return true;
        }

        foreach ($roles as $role) {
            if ($user->matchesRole($role)) {
                return true;
            }
        }

        return false;
    }
}
