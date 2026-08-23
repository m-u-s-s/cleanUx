<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Enterprise\InternalApprovalService;
use App\Services\Enterprise\ServiceLevelService;
use App\Services\Enterprise\SiteBudgetService;
use App\Services\PermissionService;
use App\Support\Organizations\ResolvesActiveOrganization;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/** LE PILOTAGE D'UNE ENTREPRISE CLIENTE, SUR LE TÉLÉPHONE — budgets (E7), approbations (E8) et niveau de service (E9). */
class GovernanceController extends Controller
{
    use ResolvesActiveOrganization;

    public function budgets(): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('finance.view');

        return response()->json([
            'data' => app(SiteBudgetService::class)->etatsEnCours((int) $org->id)->all(),
        ]);
    }

    public function serviceLevel(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('analytics.view');

        $debut = $this->dateOuDefaut($request->query('from'), Carbon::now()->startOfMonth());
        $fin = $this->dateOuDefaut($request->query('to'), Carbon::now()->endOfMonth());

        $service = app(ServiceLevelService::class);

        return response()->json([
            'data' => $service->parLocal((int) $org->id, $debut->startOfDay(), $fin->endOfDay()),
            'meta' => $service->resume((int) $org->id, $debut->startOfDay(), $fin->endOfDay()),
        ]);
    }

    public function approvals(): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('bookings.approve');

        return response()->json([
            'data' => app(InternalApprovalService::class)->enAttente((int) $org->id)
                ->map(fn (Booking $demande) => [
                    'id' => $demande->id,
                    'site_name' => $demande->organizationSite?->name,
                    'trade_name' => $demande->trade?->name,
                    'requested_by' => $demande->clientUser?->name,
                    'scheduled_at' => $demande->scheduled_at?->toIso8601String(),
                    'estimated_cents' => (int) round(((float) ($demande->devis_estime ?? 0)) * 100),
                ])->all(),
        ]);
    }

    public function decideApproval(Request $request, int $bookingId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('bookings.approve');

        $donnees = $request->validate([
            'approve' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Le scoping fait partie de la requête : la demande d'une autre société n'est jamais
        // chargée.
        $booking = Booking::query()
            ->where('customer_organization_id', $org->id)
            ->findOrFail($bookingId);

        $service = app(InternalApprovalService::class);

        try {
            $booking = $donnees['approve']
                ? $service->approuver($booking, Auth::user(), $donnees['reason'] ?? null)
                : $service->refuser($booking, Auth::user(), $donnees['reason'] ?? 'Sans motif précisé.');
        } catch (DomainException $e) {
            // « Une demande ne s'approuve pas soi-même » est une règle à LIRE, pas une panne.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $booking->id, 'status' => $booking->status]]);
    }

    private function exiger(string $permission): void
    {
        abort_unless(
            app(PermissionService::class)->can(
                Auth::user(),
                $permission,
                $this->organisationActive(),
            ),
            403
        );
    }

    private function dateOuDefaut(mixed $valeur, Carbon $defaut): Carbon
    {
        if (! is_string($valeur) || $valeur === '') {
            return $defaut;
        }

        try {
            return Carbon::parse($valeur);
        } catch (\Throwable) {
            // Une date illisible venue du client ne doit pas rendre 500 : on retombe sur la
            // fenêtre par défaut, que l'appelant lit dans `meta`.
            return $defaut;
        }
    }
}
