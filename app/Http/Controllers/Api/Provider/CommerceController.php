<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\FleetCertification;
use App\Models\FleetEquipment;
use App\Models\FleetVehicle;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\ProviderQuote;
use App\Models\ProviderQuoteLine;
use App\Services\FleetV2\ProviderFleetService;
use App\Services\PermissionService;
use App\Services\Quality\WorkerQualityScoreService;
use App\Services\Quotes\ProviderQuoteService;
use App\Services\Recruitment\RecruitmentService;
use App\Support\Organizations\ResolvesActiveOrganization;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * L'API NATIVE DES DEVIS (E24), DU RECRUTEMENT (E25), DU SCORE QUALITÉ (E26) ET DE LA FLOTTE (E27).
 *
 * POURQUOI CES QUATRE-LÀ SUR UN TÉLÉPHONE. Un devis se chiffre CHEZ LE CLIENT, pendant la visite —
 * c'est le seul moment où l'on voit la surface, l'état, les accès. Le noter pour le saisir en
 * rentrant, c'est perdre la moitié des détails et deux jours de délai de réponse. Le tri des
 * candidatures et la vérification d'une échéance de permis se font entre deux chantiers. Le score
 * qualité se consulte avant une conversation, pas après.
 *
 * DEUX RÈGLES, COMME PARTOUT DANS L'ESPACE SOCIÉTÉ : chaque requête est limitée à l'organisation
 * ACTIVE de l'appelant, et chaque écriture exige une permission. Le scoping fait partie de la
 * REQUÊTE : une ressource d'une autre société n'est jamais chargée.
 *
 * ET LES REFUS DU DOMAINE SORTENT EN 422. « Un devis envoyé ne se modifie plus » est une règle qu'il
 * faut LIRE : la remplacer par « une erreur est survenue » fait recommencer la saisie.
 */
class CommerceController extends Controller
{
    use ResolvesActiveOrganization;

    // ──────────────────────────────────────────────────────
    // E24 — les devis
    // ──────────────────────────────────────────────────────

    public function quotes(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('quotes.view');

        $devis = ProviderQuote::query()
            ->where('organization_account_id', $org->id)
            ->when($request->query('status'), fn ($q, $statut) => $q->where('status', $statut))
            ->with('client:id,name')
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $devis->map(fn (ProviderQuote $document) => $this->presenterLeDevis($document))->all(),
        ]);
    }

    public function showQuote(int $quoteId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('quotes.view');

        $devis = ProviderQuote::query()
            ->where('organization_account_id', $org->id)
            ->with(['client:id,name', 'lines.trade:id,name'])
            ->findOrFail($quoteId);

        return response()->json([
            'data' => array_merge($this->presenterLeDevis($devis), [
                'lines' => $devis->lines->map(fn (ProviderQuoteLine $ligne) => [
                    'id' => $ligne->id,
                    'label' => $ligne->label,
                    'trade_id' => $ligne->trade_id,
                    'trade_name' => $ligne->trade?->name,
                    'quantity' => $ligne->quantity,
                    'unit' => $ligne->unit,
                    'unit_price_cents' => $ligne->unit_price_cents,
                    'total_cents' => $ligne->total_cents,
                    // L'écart avec la suggestion rend la remise lisible — et dit, au bout de
                    // quelques dizaines de devis, si la société vend systématiquement sous son
                    // propre tarif.
                    'suggested_price_cents' => $ligne->suggested_price_cents,
                ])->all(),
            ]),
        ]);
    }

    public function createQuote(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('quotes.manage');

        $donnees = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'client_user_id' => ['nullable', 'integer'],
        ]);

        $devis = app(ProviderQuoteService::class)->ouvrirUnBrouillon(
            (int) $org->id,
            Auth::user(),
            $donnees['title'],
            $donnees['client_user_id'] ?? null,
        );

        return response()->json(['data' => $this->presenterLeDevis($devis)], 201);
    }

    public function addQuoteLine(Request $request, int $quoteId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('quotes.manage');

        $donnees = $request->validate([
            'trade_id' => ['required', 'integer'],
            'label' => ['required', 'string', 'max:200'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
            // Absent = on retient la SUGGESTION du moteur. Une valeur, même égale, marque une
            // décision.
            'unit_price_cents' => ['nullable', 'integer', 'min:0'],
            'service_catalog_id' => ['nullable', 'integer'],
        ]);

        $devis = ProviderQuote::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($quoteId);

        try {
            $ligne = app(ProviderQuoteService::class)->ajouterUneLigne(
                $devis,
                (int) $donnees['trade_id'],
                $donnees['label'],
                (float) ($donnees['quantity'] ?? 1),
                $donnees['unit_price_cents'] ?? null,
                $donnees['service_catalog_id'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'id' => $ligne->id,
                'total_cents' => $ligne->total_cents,
                'suggested_price_cents' => $ligne->suggested_price_cents,
                'quote_total_cents' => $devis->fresh()->total_cents,
            ],
        ], 201);
    }

    public function sendQuote(int $quoteId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('quotes.manage');

        $devis = ProviderQuote::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($quoteId);

        try {
            $devis = app(ProviderQuoteService::class)->envoyer($devis);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->presenterLeDevis($devis)]);
    }

    // ──────────────────────────────────────────────────────
    // E25 — le recrutement
    // ──────────────────────────────────────────────────────

    public function jobPostings(): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('recruitment.view');

        $offres = JobPosting::query()
            ->where('organization_account_id', $org->id)
            ->withCount('applications')
            ->with('trade:id,name')
            ->latest()
            ->get();

        return response()->json([
            'data' => $offres->map(fn (JobPosting $offre) => [
                'id' => $offre->id,
                'reference' => $offre->reference,
                'title' => $offre->title,
                'trade_name' => $offre->trade?->name,
                'status' => $offre->status,
                'applications_count' => $offre->applications_count,
            ])->all(),
        ]);
    }

    public function jobApplications(int $postingId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('recruitment.view');

        $offre = JobPosting::query()
            ->where('organization_account_id', $org->id)
            ->with('applications')
            ->findOrFail($postingId);

        return response()->json([
            'data' => $offre->applications->map(fn (JobApplication $candidature) => [
                'id' => $candidature->id,
                'full_name' => $candidature->full_name,
                'email' => $candidature->email,
                'phone' => $candidature->phone,
                'status' => $candidature->status,
                // Embaucher ÉMET l'invitation : sans ce drapeau, l'application ne pourrait pas dire
                // si le collègue a réellement été convié.
                'invited' => $candidature->organization_invitation_id !== null,
            ])->all(),
        ]);
    }

    public function decideApplication(Request $request, int $applicationId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('recruitment.manage');

        $donnees = $request->validate([
            'decision' => ['required', 'string', 'in:shortlist,hire,reject'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        /*
         * Le scoping passe par l'OFFRE : une candidature ne porte pas d'organisation, et charger
         * par son seul identifiant exposerait celles d'une autre société — c'est-à-dire des données
         * personnelles de gens qui n'ont postulé nulle part ici.
         */
        $candidature = JobApplication::query()
            ->whereHas('posting', fn ($q) => $q->where('organization_account_id', $org->id))
            ->findOrFail($applicationId);

        $service = app(RecruitmentService::class);

        try {
            $candidature = match ($donnees['decision']) {
                'shortlist' => $service->retenir($candidature, Auth::user()),
                'hire' => $service->embaucher($candidature, Auth::user()),
                default => $service->refuser($candidature, Auth::user(), $donnees['note'] ?? null),
            };
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'id' => $candidature->id,
                'status' => $candidature->status,
                'invited' => $candidature->organization_invitation_id !== null,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────
    // E26 — le score qualité interne
    // ──────────────────────────────────────────────────────

    public function qualityScores(): JsonResponse
    {
        $org = $this->organisationActive();
        /*
         * CE SCORE NE SORT PAS DE LA SOCIÉTÉ, et `missions.quality` est la clé qui le dit : il sert
         * à repérer qui a besoin d'aide, pas à classer publiquement.
         */
        $this->exiger('missions.quality');

        return response()->json([
            'data' => app(WorkerQualityScoreService::class)->pourLaSociete((int) $org->id),
            'meta' => [
                // Sous ce seuil, aucun score n'est calculé : une moyenne sur une mission est du
                // bruit affiché avec deux décimales.
                'missions_minimum' => WorkerQualityScoreService::MISSIONS_MINIMUM,
                'weights' => WorkerQualityScoreService::POIDS,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────
    // E27 — la flotte
    // ──────────────────────────────────────────────────────

    public function fleet(): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('fleet.view');

        $service = app(ProviderFleetService::class);

        return response()->json([
            'vehicles' => $service->vehicules((int) $org->id)
                ->map(fn (FleetVehicle $vehicule) => [
                    'id' => $vehicule->id,
                    'plate' => $vehicule->plate,
                    'brand' => $vehicule->brand,
                    'model' => $vehicule->model,
                    'status' => $vehicule->status,
                    'current_provider_name' => $vehicule->currentProvider?->name,
                ])->values()->all(),
            'equipment' => $service->equipements((int) $org->id)
                ->map(fn (FleetEquipment $equipement) => [
                    'id' => $equipement->id,
                    'name' => $equipement->name,
                    'equipment_type' => $equipement->equipment_type,
                    'status' => $equipement->status,
                ])->values()->all(),
            /*
             * LA SEULE LECTURE QUI CHANGE QUELQUE CHOSE. Le reste est un inventaire ; celle-ci évite
             * qu'une assignation soit refusée un matin sans que personne ne sache pourquoi.
             */
            'expiring' => $service->echeances((int) $org->id)
                ->map(fn (FleetCertification $certification) => [
                    'id' => $certification->id,
                    'certification_type' => $certification->certification_type,
                    'subject_type' => $certification->subject_type,
                    'subject_id' => $certification->subject_id,
                    'expires_at' => $certification->expires_at?->toDateString(),
                    'status' => $certification->status,
                ])->values()->all(),
            'meta' => [
                'can_manage' => app(PermissionService::class)->can(Auth::user(), 'fleet.manage', $org),
                'notice_days' => ProviderFleetService::PREAVIS_JOURS,
            ],
        ]);
    }

    public function declareVehicle(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exiger('fleet.manage');

        $donnees = $request->validate([
            'plate' => ['required', 'string', 'max:20'],
            'brand' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:60'],
            'vehicle_type' => ['nullable', 'string', 'max:40'],
        ]);

        $vehicule = app(ProviderFleetService::class)->declarerUnVehicule((int) $org->id, $donnees);

        return response()->json([
            'data' => ['id' => $vehicule->id, 'plate' => $vehicule->plate, 'status' => $vehicule->status],
        ], 201);
    }

    // ──────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function presenterLeDevis(ProviderQuote $devis): array
    {
        return [
            'id' => $devis->id,
            'reference' => $devis->reference,
            'title' => $devis->title,
            'client_name' => $devis->client?->name,
            'status' => $devis->status,
            'total_cents' => $devis->total_cents,
            'currency' => $devis->currency,
            'valid_until' => $devis->valid_until?->toDateString(),
            'sent_at' => $devis->sent_at?->toIso8601String(),
            // L'échéance compte MÊME SI le balayage n'est pas passé : la validité d'un devis ne doit
            // pas dépendre de l'heure du cron.
            'is_open' => $devis->estOuvert(),
        ];
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
}
