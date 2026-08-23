<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\LeaveRequest;
use App\Models\Mission;
use App\Models\OrganizationMember;
use App\Models\Shift;
use App\Models\TimeEntry;
use App\Services\Inventory\InventoryService;
use App\Services\PermissionService;
use App\Services\Workforce\LeaveService;
use App\Services\Workforce\ProfitabilityService;
use App\Services\Workforce\TimesheetService;
use App\Support\Organizations\ResolvesActiveOrganization;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/** L'API NATIVE DU PLANNING (E19), DES HEURES (E20), DES ABSENCES (E21), DE LA RENTABILITÉ (E22) ET DES CONSOMMABLES (E23). */
class WorkforceController extends Controller
{
    use ResolvesActiveOrganization;

    // ──────────────────────────────────────────────────────
    // E19 — le planning
    // ──────────────────────────────────────────────────────

    /** Les créneaux d'une semaine, avec le mien mis en avant côté application. */
    public function shifts(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('team.view');

        $debut = $this->dateOuDefaut($request->query('from'), Carbon::now()->startOfWeek());
        $fin = $this->dateOuDefaut($request->query('to'), $debut->copy()->endOfWeek());

        $creneaux = Shift::query()
            ->where('organization_account_id', $org->id)
            ->where('status', '!=', Shift::STATUS_CANCELLED)
            ->whereBetween('starts_at', [$debut->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->with('user:id,name')
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'data' => $creneaux->map(fn (Shift $creneau) => [
                'id' => $creneau->id,
                'user_id' => $creneau->user_id,
                'user_name' => $creneau->user?->name,
                'starts_at' => $creneau->starts_at->toIso8601String(),
                'ends_at' => $creneau->ends_at->toIso8601String(),
                'status' => $creneau->status,
                // Un brouillon ne rend PAS assignable : l'application doit pouvoir le dire, sinon
                // quelqu'un compte sur un horaire que personne ne lui a communiqué.
                'is_published' => $creneau->status === Shift::STATUS_PUBLISHED,
            ])->all(),
            'meta' => ['from' => $debut->toDateString(), 'to' => $fin->toDateString()],
        ]);
    }

    public function createShift(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('team.manage');

        $donnees = $request->validate([
            'user_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        // La cible doit être un membre ACTIF de cette société : sans cette garde, on planifierait
        // l'employé d'une autre entreprise.
        OrganizationMember::query()
            ->where('organization_account_id', $org->id)
            ->where('user_id', $donnees['user_id'])
            ->where('status', 'active')
            ->firstOrFail();

        $creneau = Shift::query()->create([
            'organization_account_id' => $org->id,
            'user_id' => $donnees['user_id'],
            'starts_at' => Carbon::parse($donnees['starts_at']),
            'ends_at' => Carbon::parse($donnees['ends_at']),
            // On ne publie pas en créant : une semaine se construit ligne à ligne.
            'status' => Shift::STATUS_PLANNED,
        ]);

        return response()->json(['data' => ['id' => $creneau->id, 'status' => $creneau->status]], 201);
    }

    /** Publier une semaine : c'est le geste qui rend l'équipe assignable. */
    public function publishShifts(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('team.manage');

        $debut = $this->dateOuDefaut($request->input('from'), Carbon::now()->startOfWeek());
        $fin = $this->dateOuDefaut($request->input('to'), $debut->copy()->endOfWeek());

        $publies = Shift::query()
            ->where('organization_account_id', $org->id)
            ->where('status', Shift::STATUS_PLANNED)
            ->whereBetween('starts_at', [$debut->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->update(['status' => Shift::STATUS_PUBLISHED]);

        return response()->json(['data' => ['published' => $publies]]);
    }

    public function cancelShift(int $shiftId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('team.manage');

        Shift::query()
            ->where('organization_account_id', $org->id)
            ->whereKey($shiftId)
            ->update(['status' => Shift::STATUS_CANCELLED]);

        return response()->json(['data' => ['cancelled' => true]]);
    }

    // ──────────────────────────────────────────────────────
    // E21 — les absences
    // ──────────────────────────────────────────────────────

    public function leaves(Request $request): JsonResponse
    {
        $org = $this->organisationActive();

        $debut = $this->dateOuDefaut($request->query('from'), Carbon::now()->startOfMonth());
        $fin = $this->dateOuDefaut($request->query('to'), Carbon::now()->addMonths(3)->endOfMonth());

        $peutGerer = app(PermissionService::class)->can(Auth::user(), 'team.manage', $org);

        $demandes = app(LeaveService::class)
            ->surLaPeriode((int) $org->id, $debut, $fin, $request->query('status'))
            // SANS `team.manage`, ON NE VOIT QUE LES SIENNES.
            ->when(! $peutGerer, fn ($c) => $c->where('user_id', Auth::id()))
            ->values();

        return response()->json([
            'data' => $demandes->map(fn (LeaveRequest $demande) => [
                'id' => $demande->id,
                'user_id' => $demande->user_id,
                'user_name' => $demande->user?->name,
                'type' => $demande->type,
                'starts_on' => $demande->starts_on->toDateString(),
                'ends_on' => $demande->ends_on->toDateString(),
                'status' => $demande->status,
                'reason' => $demande->reason,
                // Seul `approved` bloque le planning : l'application doit pouvoir le dire.
                'blocks_planning' => $demande->status === LeaveRequest::STATUS_APPROVED,
            ])->all(),
            'meta' => ['can_manage' => $peutGerer],
        ]);
    }

    /** Poser SA propre absence. On ne pose pas un congé pour quelqu'un d'autre. */
    public function requestLeave(Request $request): JsonResponse
    {
        $org = $this->organisationActive();

        $donnees = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date'],
            'type' => ['nullable', 'string', 'in:paid,unpaid,sick,other'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $demande = app(LeaveService::class)->demander(
                Auth::user(),
                (int) $org->id,
                Carbon::parse($donnees['starts_on']),
                Carbon::parse($donnees['ends_on']),
                $donnees['type'] ?? 'paid',
                $donnees['reason'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $demande->id, 'status' => $demande->status]], 201);
    }

    public function decideLeave(Request $request, int $leaveId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('team.manage');

        $donnees = $request->validate([
            'approve' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // Scopé sur l'organisation : un identifiant forgé ne doit pas atteindre l'absence d'une
        // autre société.
        $demande = LeaveRequest::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($leaveId);

        try {
            $demande = app(LeaveService::class)->statuer(
                $demande,
                Auth::user(),
                (bool) $donnees['approve'],
                $donnees['note'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $demande->id, 'status' => $demande->status]]);
    }

    public function cancelLeave(int $leaveId): JsonResponse
    {
        $org = $this->organisationActive();

        $demande = LeaveRequest::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($leaveId);

        try {
            $demande = app(LeaveService::class)->annuler($demande, Auth::user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $demande->id, 'status' => $demande->status]]);
    }

    // ──────────────────────────────────────────────────────
    // E20 / E22 — les heures et ce qu'elles coûtent
    // ──────────────────────────────────────────────────────

    public function timesheets(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('team.view');

        $debut = $this->dateOuDefaut($request->query('from'), Carbon::now()->startOfMonth());
        $fin = $this->dateOuDefaut($request->query('to'), Carbon::now()->endOfMonth());

        $feuille = app(TimesheetService::class)
            ->feuilleDeLaPeriode((int) $org->id, $debut->copy()->startOfDay(), $fin->copy()->endOfDay());

        $enAttente = TimeEntry::query()
            ->where('organization_account_id', $org->id)
            ->where('status', TimeEntry::STATUS_PENDING_APPROVAL)
            ->with('user:id,name')
            ->orderBy('started_at')
            ->get();

        return response()->json([
            'data' => $feuille->all(),
            'pending' => $enAttente->map(fn (TimeEntry $ligne) => [
                'id' => $ligne->id,
                'user_id' => $ligne->user_id,
                'user_name' => $ligne->user?->name,
                'started_at' => $ligne->started_at->toIso8601String(),
                'worked_minutes' => $ligne->worked_minutes,
                'notes' => $ligne->notes,
            ])->all(),
            'meta' => ['from' => $debut->toDateString(), 'to' => $fin->toDateString()],
        ]);
    }

    /** Une correction saisie sur le terrain — qui attend une approbation. */
    public function recordTime(Request $request): JsonResponse
    {
        $org = $this->organisationActive();

        $donnees = $request->validate([
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date'],
            'mission_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $mission = null;

        if (! empty($donnees['mission_id'])) {
            // Scopé : on ne rattache pas des heures à la mission d'une autre société.
            $mission = Mission::query()
                ->where('provider_organization_id', $org->id)
                ->find($donnees['mission_id']);
        }

        try {
            $entry = app(TimesheetService::class)->saisirManuellement(
                Auth::user(),
                (int) $org->id,
                Carbon::parse($donnees['started_at']),
                Carbon::parse($donnees['ended_at']),
                $mission,
                $donnees['reason'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'worked_minutes' => $entry->worked_minutes,
                'status' => $entry->status,
            ],
        ], 201);
    }

    public function decideTimeEntry(Request $request, int $entryId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('team.manage');

        $donnees = $request->validate(['approve' => ['required', 'boolean']]);

        $entry = TimeEntry::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($entryId);

        try {
            $entry = app(TimesheetService::class)->statuer($entry, Auth::user(), (bool) $donnees['approve']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $entry->id, 'status' => $entry->status]]);
    }

    public function profitability(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        // LA MARGE N'EST PAS UNE DONNÉE D'ÉQUIPE.
        $this->exiger('analytics.view');

        $debut = $this->dateOuDefaut($request->query('from'), Carbon::now()->startOfMonth());
        $fin = $this->dateOuDefaut($request->query('to'), Carbon::now()->endOfMonth());

        $par = in_array($request->query('by'), ['site', 'team', 'agency'], true)
            ? (string) $request->query('by')
            : 'site';

        $lignes = app(ProfitabilityService::class)->pourLaPeriode(
            (int) $org->id,
            $debut->copy()->startOfDay(),
            $fin->copy()->endOfDay(),
            $par,
        );

        return response()->json([
            'data' => $lignes->values()->all(),
            'meta' => [
                'by' => $par,
                'from' => $debut->toDateString(),
                'to' => $fin->toDateString(),
                // Le taux est une HYPOTHÈSE : l'application doit pouvoir le dire plutôt que de
                // présenter la marge comme un fait établi.
                'default_hourly_rate_cents' => ProfitabilityService::DEFAULT_HOURLY_COST_CENTS,
                'missions_without_timesheet' => (int) $lignes->sum('missions_without_timesheet'),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────
    // E23 — les consommables
    // ──────────────────────────────────────────────────────

    public function inventory(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        // Voir n'est pas commander : `inventory.view` va jusqu'aux exécutants, qui ont besoin de
        // savoir ce qui reste AVANT de partir.
        $this->exiger('inventory.view');

        $articles = InventoryItem::query()
            ->where('organization_account_id', $org->id)
            ->where('is_active', true)
            ->when($request->query('agency_id'), fn ($q, $id) => $q->where('provider_agency_id', $id))
            ->with('agency:id,name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $articles->map(fn (InventoryItem $article) => [
                'id' => $article->id,
                'name' => $article->name,
                'unit' => $article->unit,
                'quantity' => $article->quantity,
                'reorder_threshold' => $article->reorder_threshold,
                'agency_name' => $article->agency?->name,
                'needs_reorder' => $article->quantity <= $article->reorder_threshold,
            ])->all(),
            'meta' => [
                'can_manage' => app(PermissionService::class)->can(Auth::user(), 'inventory.manage', $org),
            ],
        ]);
    }

    /** Un mouvement de stock. */
    public function moveInventory(Request $request, int $itemId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('inventory.manage');

        $donnees = $request->validate([
            'type' => ['required', 'string', 'in:reception,consumption,adjustment'],
            'quantity' => ['required', 'integer'],
            'mission_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $article = InventoryItem::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($itemId);

        $mission = null;

        if (! empty($donnees['mission_id'])) {
            $mission = Mission::query()
                ->where('provider_organization_id', $org->id)
                ->find($donnees['mission_id']);
        }

        try {
            $service = app(InventoryService::class);

            $article = match ($donnees['type']) {
                'reception' => $service->receptionner($article, (int) $donnees['quantity'], Auth::user(), $donnees['reason'] ?? null),
                'consumption' => $service->consommer($article, (int) $donnees['quantity'], Auth::user(), $mission, $donnees['reason'] ?? null),
                default => $service->ajuster($article, (int) $donnees['quantity'], Auth::user(), (string) ($donnees['reason'] ?? '')),
            };
        } catch (DomainException $e) {
            // « Il ne reste que trois cartons » est une réponse, pas une panne.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => ['id' => $article->id, 'quantity' => $article->quantity],
        ]);
    }

    public function inventoryMovements(int $itemId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('inventory.view');

        $article = InventoryItem::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($itemId);

        $mouvements = InventoryMovement::query()
            ->where('inventory_item_id', $article->id)
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $mouvements->map(fn (InventoryMovement $mouvement) => [
                'id' => $mouvement->id,
                'type' => $mouvement->type,
                // La quantité est SIGNÉE : positive à la réception, négative à la consommation. Un
                // seul signe obligerait chaque lecteur à savoir dans quel sens compter.
                'quantity' => $mouvement->quantity,
                'reason' => $mouvement->reason,
                'user_name' => $mouvement->user?->name,
                'created_at' => $mouvement->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    // ──────────────────────────────────────────────────────

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
