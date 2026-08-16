<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\RevocationDesAcces;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @group User Profile
 *
 * @authenticated
 *
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
            'ok' => true,
            'user' => $this->serialize($user),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'locale' => ['nullable', 'string', 'in:fr,nl,en'],
            // Pour changer le password, exiger l'ancien
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $motDePasseChange = false;

        if (! empty($data['password'])) {
            if (! Hash::check($data['current_password'], $user->password)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Mot de passe actuel incorrect.',
                ], 422);
            }
            $user->password = Hash::make($data['password']);
            $motDePasseChange = true;
        }

        foreach (['name', 'phone', 'locale'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $user->{$field} = $data[$field];
            }
        }

        $user->save();

        /*
         * UN CHANGEMENT DE MOT DE PASSE COUPE LES AUTRES ACCÈS.
         *
         * Ce chemin-là est celui du téléphone : on épargne le jeton COURANT — se faire déconnecter
         * du geste qu'on vient de faire ferait croire à un échec — et on révoque tous les autres,
         * plus les sessions web enregistrées et le cookie « se souvenir de moi ». Sans cela,
         * changer son mot de passe depuis l'application laissait l'ordinateur du voleur connecté.
         */
        if ($motDePasseChange) {
            app(RevocationDesAcces::class)->apresChangementDeMotDePasse(
                $user,
                $this->jetonAConserver($user->currentAccessToken()),
            );
        }
        $user->loadMissing('providerProfile');

        return response()->json([
            'ok' => true,
            'user' => $this->serialize($user),
        ]);
    }

    /**
     * L'identifiant du jeton courant, ou `null` s'il n'en a pas.
     *
     * `currentAccessToken()` ne rend PAS toujours une ligne de la table : quand la session vient du
     * cookie web — ou d'un `Sanctum::actingAs` en test — c'est un `TransientToken`, un objet sans
     * clé. Lui demander son identifiant lèverait une erreur là où il n'y a simplement rien à
     * épargner.
     *
     * Le paramètre est typé `?object` À DESSEIN : la signature de Sanctum annonce un modèle, si bien
     * qu'un `instanceof` écrit au point d'appel est déclaré « toujours vrai » par l'analyse statique
     * alors qu'il est faux à l'exécution. Le contrôle vit donc ici, où le type large le rend lisible
     * plutôt que suspect.
     */
    private function jetonAConserver(?object $jeton): ?int
    {
        return $jeton instanceof Model ? (int) $jeton->getKey() : null;
    }

    protected function serialize($user): array
    {
        $base = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? null,
            'platform_role' => $user->platform_role ?? null,
            'is_client' => method_exists($user, 'isClient') && $user->isClient(),
            'is_employe' => method_exists($user, 'isEmploye') && $user->isEmploye(),
            'is_entreprise' => method_exists($user, 'isEntreprise') && $user->isEntreprise(),
            'locale' => $user->locale ?? 'fr',
            'preferred_currency' => $user->preferred_currency ?? 'EUR',
            'organization_account_id' => $user->organization_account_id ?? $user->current_organization_id ?? null,
            'is_provider' => method_exists($user, 'isProvider') && $user->isProvider(),
            'is_admin' => method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];

        if ($user->providerProfile) {
            $p = $user->providerProfile;
            $base['provider'] = [
                'is_online' => (bool) ($p->is_online ?? false),
                'verification_status' => $p->verification_status,
                'hourly_rate' => $p->hourly_rate,
                'commission_rate' => $p->commission_rate,
                'stripe_connect_status' => $p->stripe_connect_status,
                'last_location_at' => $p->last_location_at?->toIso8601String(),
            ];
        }

        return $base;
    }
}
