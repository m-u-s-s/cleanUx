<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\SigningAppointment;
use App\Models\Trade;
use App\Services\Bookings\MultiSiteRequestService;
use App\Services\Contracts\SigningAppointmentService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * L'API DE L'ESPACE SOCIÉTÉ CLIENTE.
 *
 * `routes/api/client.php` n'exposait que `/client/companies` — l'ANNUAIRE des sociétés
 * prestataires à parcourir, sans rapport avec la société de l'appelant — et `/client/bookings`.
 * L'application mobile ne pouvait donc rien montrer de la société : ni locaux, ni membres, ni
 * rendez-vous de signature, ni demande multi-locaux.
 *
 * Mêmes deux règles que son pendant prestataire, sans exception :
 *   1. toute requête est limitée à l'organisation ACTIVE de l'appelant ;
 *   2. toute écriture exige une permission, jamais la seule appartenance.
 *
 * Les services métier sont RÉUTILISÉS tels quels — `MultiSiteRequestService` et
 * `SigningAppointmentService`, écrits en phase 1 pour le web. Les réimplémenter ferait diverger
 * deux surfaces qui doivent appliquer les mêmes règles : filtrage des locaux sur l'organisation,
 * refus d'une demande sans local recevable, refus d'une date passée.
 */
class CompanyController extends Controller
{
    public function sites(): JsonResponse
    {
        $org = $this->organisationActive();

        $locaux = OrganizationSite::query()
            ->where('organization_account_id', $org->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'site_code', 'city', 'address_line_1'])
            ->map(fn (OrganizationSite $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->site_code,
                'city' => $s->city,
                'address' => $s->address_line_1,
            ]);

        return response()->json(['data' => $locaux]);
    }

    public function members(): JsonResponse
    {
        $org = $this->organisationActive();

        $membres = OrganizationMember::query()
            ->where('organization_account_id', $org->id)
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get()
            ->map(fn (OrganizationMember $m) => [
                'id' => $m->id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'role' => $m->role->value,
                'status' => $m->status,
            ]);

        return response()->json(['data' => $membres]);
    }

    /**
     * Une même prestation demandée pour plusieurs locaux d'un coup.
     *
     * Le service filtre lui-même les locaux sur l'organisation et refuse une demande sans local
     * recevable : on ne redouble pas ce contrôle ici, on s'appuie dessus.
     */
    public function multiSiteRequest(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('bookings.create', $org);

        $donnees = $request->validate([
            'site_ids' => ['required', 'array', 'min:1'],
            'site_ids.*' => ['integer'],
            'trade_id' => ['required', 'integer'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $trade = Trade::find($donnees['trade_id']);

        if (! $trade) {
            return response()->json(['message' => 'Métier introuvable.'], 422);
        }

        $demande = app(MultiSiteRequestService::class)->creer(
            Auth::user(),
            $org,
            $trade,
            array_map('intval', $donnees['site_ids']),
            Carbon::parse($donnees['scheduled_at']),
            [
                'duree_estimee' => $donnees['duration_minutes'] ?? 60,
                'commentaire_client' => $donnees['comment'] ?? null,
            ],
        );

        if (! $demande) {
            return response()->json(['message' => 'Aucun local valide dans la sélection.'], 422);
        }

        return response()->json(['data' => ['id' => $demande->id]], 201);
    }

    public function signingAppointments(): JsonResponse
    {
        $org = $this->organisationActive();

        $rendezVous = SigningAppointment::query()
            ->where('organization_account_id', $org->id)
            ->with(['organizationSite:id,name', 'signer:id,name'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (SigningAppointment $r) => [
                'id' => $r->id,
                // `scheduled_at` est NOT NULL en base et casté en date : jamais nul ici.
                'scheduled_at' => $r->scheduled_at->toIso8601String(),
                'status' => $r->status,
                'site' => $r->organizationSite?->name,
                'signer' => $r->signer?->name,
                'notes' => $r->notes,
            ]);

        return response()->json(['data' => $rendezVous]);
    }

    public function createSigningAppointment(Request $request): JsonResponse
    {
        $org = $this->organisationActive();

        $donnees = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'organization_site_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /*
         * UN LOCAL DEMANDÉ MAIS INTROUVABLE EST UN REFUS, PAS UN SILENCE.
         *
         * Le local n'est chargé que s'il appartient à l'organisation. Ma première version se
         * contentait de laisser `$site` à `null` dans le cas contraire — et le service planifiait
         * alors un rendez-vous « à distance ». La demande « signer au local X » devenait
         * silencieusement « signer ailleurs », sans que personne ne le sache.
         *
         * On distingue donc les deux cas : aucun local demandé (légitime, signature à distance) et
         * local demandé mais étranger à la société (refus explicite).
         */
        $site = null;

        if (isset($donnees['organization_site_id'])) {
            $site = OrganizationSite::query()
                ->where('organization_account_id', $org->id)
                ->find($donnees['organization_site_id']);

            if (! $site) {
                return response()->json([
                    'message' => "Ce local n'appartient pas à votre société.",
                ], 422);
            }
        }

        $rdv = app(SigningAppointmentService::class)->planifier(
            $org,
            Auth::user(),
            Carbon::parse($donnees['scheduled_at']),
            $site,
            null,
            $donnees['notes'] ?? null,
        );

        if (! $rdv) {
            // Date passée, ou local demandé mais introuvable dans cette société.
            return response()->json([
                'message' => 'Rendez-vous impossible : vérifiez la date et le local choisis.',
            ], 422);
        }

        return response()->json(['data' => ['id' => $rdv->id]], 201);
    }

    /**
     * L'organisation active de l'appelant.
     *
     * Un compte sans organisation n'a rien à faire sur cette API : 403 explicite plutôt qu'une
     * liste vide, qui laisserait croire à une société sans locaux ni membres.
     */
    private function organisationActive(): OrganizationAccount
    {
        $organisation = Auth::user()?->currentOrganization;

        abort_if($organisation === null, 403);

        return $organisation;
    }

    private function exige(string $permission, OrganizationAccount $organisation): void
    {
        abort_unless(
            app(PermissionService::class)->can(Auth::user(), $permission, $organisation),
            403
        );
    }
}
