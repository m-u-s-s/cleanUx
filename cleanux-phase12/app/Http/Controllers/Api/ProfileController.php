<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Phase 12 — Endpoints profil utilisateur.
 *
 * GET   /api/profile  → profil détaillé (incluant providerProfile si applicable)
 * PATCH /api/profile  → update champs autorisés (name, phone, locale, password)
 */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('providerProfile');

        return response()->json([
            'ok'   => true,
            'user' => $this->serialize($user),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'   => ['nullable', 'string', 'max:255'],
            'phone'  => ['nullable', 'string', 'max:30'],
            'locale' => ['nullable', 'string', 'in:fr,nl,en'],
            // Pour changer le password, exiger l'ancien
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password'         => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (! empty($data['password'])) {
            if (! Hash::check($data['current_password'], $user->password)) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'Mot de passe actuel incorrect.',
                ], 422);
            }
            $user->password = Hash::make($data['password']);
        }

        foreach (['name', 'phone', 'locale'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $user->{$field} = $data[$field];
            }
        }

        $user->save();
        $user->loadMissing('providerProfile');

        return response()->json([
            'ok'   => true,
            'user' => $this->serialize($user),
        ]);
    }

    protected function serialize($user): array
    {
        $base = [
            'id'                       => $user->id,
            'name'                     => $user->name,
            'email'                    => $user->email,
            'phone'                    => $user->phone ?? null,
            'role'                     => $user->role ?? null,
            'platform_role'            => $user->platform_role ?? null,
            'locale'                   => $user->locale ?? 'fr',
            'preferred_currency'       => $user->preferred_currency ?? 'EUR',
            'organization_account_id'  => $user->organization_account_id ?? $user->current_organization_id ?? null,
            'is_provider'              => method_exists($user, 'isProvider') && $user->isProvider(),
            'is_admin'                 => method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin(),
            'created_at'               => $user->created_at?->toIso8601String(),
        ];

        if ($user->providerProfile) {
            $p = $user->providerProfile;
            $base['provider'] = [
                'is_online'              => (bool) ($p->is_online ?? false),
                'verification_status'    => $p->verification_status,
                'hourly_rate'            => $p->hourly_rate,
                'commission_rate'        => $p->commission_rate,
                'stripe_connect_status'  => $p->stripe_connect_status,
                'last_location_at'       => $p->last_location_at?->toIso8601String(),
            ];
        }

        return $base;
    }
}
