<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\SafetyAlert;
use App\Services\Safety\SafetyAlertService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/** LE BOUTON D'URGENCE (E33). C'EST LE POINT D'API LE PLUS CRITIQUE DE TOUTE LA PLATEFORME. */
class SafetyController extends Controller
{
    public function trigger(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'level' => ['nullable', 'string', 'in:check_in,emergency'],
            'mission_id' => ['nullable', 'integer'],
            'message' => ['nullable', 'string', 'max:500'],
            // Optionnelles : une alerte sans position vaut infiniment mieux qu'un 422 renvoyé à
            // quelqu'un qui a peur.
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'accuracy_m' => ['nullable', 'integer'],
        ]);

        $mission = null;

        if (! empty($donnees['mission_id'])) {
            // La mission est celle de l'appelant, ou rien : on ne rattache pas une alerte à
            // l'intervention d'un autre. Mais son absence n'empêche PAS l'alerte.
            $mission = Mission::query()
                ->where('lead_provider_user_id', Auth::id())
                ->find($donnees['mission_id']);
        }

        $alerte = app(SafetyAlertService::class)->declencher(
            Auth::user(),
            $donnees['level'] ?? SafetyAlert::LEVEL_EMERGENCY,
            $mission,
            $donnees['message'] ?? null,
            array_filter([
                'lat' => $donnees['lat'] ?? null,
                'lng' => $donnees['lng'] ?? null,
                'accuracy_m' => $donnees['accuracy_m'] ?? null,
            ], fn ($valeur) => $valeur !== null),
        );

        return response()->json(['data' => $this->presenter($alerte)], 201);
    }

    /** Une position de plus, pendant l'alerte. */
    public function ping(Request $request, int $alertId): JsonResponse
    {
        $donnees = $request->validate([
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'accuracy_m' => ['nullable', 'integer'],
            'pinged_at' => ['nullable', 'date'],
        ]);

        $alerte = $this->mienne($alertId);

        app(SafetyAlertService::class)->pointer(
            $alerte,
            (float) $donnees['lat'],
            (float) $donnees['lng'],
            $donnees['accuracy_m'] ?? null,
            isset($donnees['pinged_at']) ? Carbon::parse($donnees['pinged_at']) : null,
        );

        return response()->json(['data' => ['recorded' => true]]);
    }

    /** L'alerte en cours, s'il y en a une — ce que l'application interroge à l'ouverture. */
    public function current(): JsonResponse
    {
        $alerte = app(SafetyAlertService::class)->alerteOuverteDe(Auth::user());

        return response()->json([
            'data' => $alerte === null ? null : $this->presenter($alerte),
        ]);
    }

    /** Le prestataire lui-même referme son alerte — « tout va bien ». */
    public function close(Request $request, int $alertId): JsonResponse
    {
        $donnees = $request->validate([
            'false_alarm' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $alerte = $this->mienne($alertId);

        try {
            $alerte = app(SafetyAlertService::class)->cloturer(
                $alerte,
                Auth::user(),
                (bool) ($donnees['false_alarm'] ?? false),
                $donnees['note'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->presenter($alerte)]);
    }

    /** Une alerte À MOI, ou 404 : on ne referme pas celle d'un autre. */
    private function mienne(int $alertId): SafetyAlert
    {
        /** @var SafetyAlert $alerte */
        $alerte = SafetyAlert::query()
            ->where('user_id', Auth::id())
            ->findOrFail($alertId);

        return $alerte;
    }

    /** @return array<string, mixed> */
    private function presenter(SafetyAlert $alerte): array
    {
        return [
            'id' => $alerte->id,
            'level' => $alerte->level,
            'status' => $alerte->status,
            // Savoir que quelqu'un a VU l'alerte est ce que la personne sur place attend en
            // premier — plus que la résolution.
            'acknowledged_at' => $alerte->acknowledged_at?->toIso8601String(),
            'created_at' => $alerte->created_at?->toIso8601String(),
            'contact_notified' => $alerte->contact_notified_at !== null,
        ];
    }
}
