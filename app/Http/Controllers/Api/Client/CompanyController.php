<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Services\PermissionService;
use App\Support\Finance\ClientFinanceDocumentScope;
use App\Support\Organizations\ResolvesActiveOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * L'API DE L'ESPACE SOCIÉTÉ CLIENTE.
 *
 * POURQUOI CE FICHIER EXISTE. `config/parity.php` déclarait six modules `entreprise-client` en
 * `mobile => 'webview'` — Accueil, Locaux, Réservations, Membres, Facturation, Contrats. Aucun
 * n'était joignable depuis l'application cliente : pas d'écran natif, pas de lien WebView, et
 * `ModuleHubScreen`, seule porte générique vers ces modules, monté nulle part.
 *
 * Ce contrôleur est le pendant exact de `Api\Provider\CompanyController`, et il obéit aux deux
 * mêmes règles, sans exception :
 *   1. toute requête est limitée à l'organisation ACTIVE de l'appelant ;
 *   2. la lecture sensible comme l'écriture exigent une permission, jamais la seule appartenance.
 *
 * Les identifiants reçus ne sont jamais tenus pour fiables : le scoping fait partie de la REQUÊTE,
 * si bien qu'une ressource d'une autre société n'est jamais chargée — donc jamais divulguée, même
 * par la différence entre un 403 et un 404.
 *
 * Les permissions retenues sont celles que les écrans web appliquent DÉJÀ (`SiteManager` →
 * `sites.view_all`, `BillingCenter` → `finance.view`) : deux surfaces qui gardent la même donnée
 * par deux règles différentes finissent toujours par diverger, et c'est la plus permissive qui
 * décide.
 */
class CompanyController extends Controller
{
    use ResolvesActiveOrganization;

    // ──────────────────────────────────────────────────────
    // Accueil
    // ──────────────────────────────────────────────────────

    public function overview(): JsonResponse
    {
        $org = $this->organisationActive();

        $reservations = fn () => Booking::query()->where('customer_organization_id', $org->id);

        $kpis = [
            'sites_count' => OrganizationSite::forOrg($org->id)->active()->count(),
            'bookings_active' => $reservations()
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->count(),
            'bookings_month' => $reservations()
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'pending_approval' => $reservations()->where('status', 'pending_approval')->count(),
            'members_count' => OrganizationMember::query()
                ->where('organization_account_id', $org->id)
                ->where('status', 'active')
                ->count(),
        ];

        $recentes = $reservations()
            ->with(['organizationSite:id,name', 'providerUser:id,name'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Booking $b) => $this->presenteReservation($b));

        return response()->json([
            'data' => [
                'organization' => ['id' => $org->id, 'name' => $org->name],
                'kpis' => $kpis,
                'recent_bookings' => $recentes,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Locaux
    // ──────────────────────────────────────────────────────

    public function sites(): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('sites.view_all', $org);

        $locaux = OrganizationSite::forOrg($org->id)
            ->withCount(['bookings as active_bookings_count' => fn (Builder $q) => $q->whereIn('status', ['confirmed', 'in_progress'])])
            ->orderBy('name')
            ->get()
            ->map(fn (OrganizationSite $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'address' => $s->address,
                'city' => $s->city,
                'postal_code' => $s->postal_code,
                'status' => $s->status,
                'surface_m2' => $s->surface_m2,
                'floor_count' => $s->floor_count,
                'contact_name' => $s->contact_name,
                'contact_phone' => $s->contact_phone,
                // La colonne historique `cleaning_frequency` a été généralisée en
                // `service_frequency` (migration A2) sans que l'ancienne disparaisse : les deux
                // cohabitent, et une base ancienne ne porte que la première.
                // `cleaning_frequency` n'est pas declaree par le modele : on la lit comme
                // l'attribut dynamique qu'elle est, exactement comme `active_bookings_count`
                // ci-dessous. Sans cela l'analyse statique la signale, a juste titre.
                'service_frequency' => $s->service_frequency ?? $s->getAttributeValue('cleaning_frequency'),
                // `withCount` pose un attribut dynamique que le modèle ne déclare pas : on le lit
                // comme tel plutôt que d'ajouter une propriété qui n'existe qu'ici.
                'active_bookings_count' => (int) $s->getAttributeValue('active_bookings_count'),
            ]);

        return response()->json(['data' => $locaux]);
    }

    public function createSite(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('sites.create', $org);

        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'surface_m2' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'floor_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'access_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $local = OrganizationSite::create($donnees + [
            'organization_account_id' => $org->id,
            'country' => 'BE',
            'status' => 'active',
        ]);

        return response()->json(['data' => ['id' => $local->id, 'name' => $local->name]], 201);
    }

    // ──────────────────────────────────────────────────────
    // Réservations
    // ──────────────────────────────────────────────────────

    public function bookings(Request $request): JsonResponse
    {
        $org = $this->organisationActive();

        $reservations = Booking::query()
            ->where('customer_organization_id', $org->id)
            ->when(
                $request->string('status')->isNotEmpty(),
                fn ($q) => $q->where('status', $request->string('status')->toString())
            )
            ->when(
                $request->integer('site_id') > 0,
                // Le local est re-filtré sur l'organisation : un identifiant reçu ne suffit
                // jamais à désigner une ressource.
                fn ($q) => $q->whereIn(
                    'organization_site_id',
                    OrganizationSite::forOrg($org->id)->where('id', $request->integer('site_id'))->pluck('id')
                )
            )
            ->with(['organizationSite:id,name', 'providerUser:id,name'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Booking $b) => $this->presenteReservation($b));

        return response()->json(['data' => $reservations]);
    }

    // ──────────────────────────────────────────────────────
    // Membres
    // ──────────────────────────────────────────────────────

    public function members(): JsonResponse
    {
        $org = $this->organisationActive();

        $membres = OrganizationMember::query()
            ->where('organization_account_id', $org->id)
            ->with('user:id,name,email,profile_photo_path')
            ->orderBy('id')
            ->get()
            ->map(fn (OrganizationMember $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                // `role` est CASTÉ en enum par le modèle — pas de normalisation défensive ici,
                // elle serait morte (voir la même leçon côté prestataire).
                'role' => $m->role->value,
                'status' => $m->status,
                'joined_at' => $m->joined_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $membres]);
    }

    // ──────────────────────────────────────────────────────
    // Contrats — LECTURE SEULE, comme le portail web
    // ──────────────────────────────────────────────────────

    public function contracts(): JsonResponse
    {
        $org = $this->organisationActive();

        $contrats = OrganizationContract::query()
            ->where('organization_account_id', $org->id)
            ->with('providerOrganization:id,name')
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn (OrganizationContract $c) => [
                'id' => $c->id,
                'reference' => $c->contract_reference,
                'status' => $c->status,
                'provider' => $c->providerOrganization?->name,
                'billing_cycle' => $c->billing_cycle,
                'effective_from' => $c->effective_from?->toDateString(),
                'effective_to' => $c->effective_to?->toDateString(),
                'payment_terms_days' => $c->payment_terms_days,
            ]);

        return response()->json(['data' => $contrats]);
    }

    // ──────────────────────────────────────────────────────
    // Facturation
    // ──────────────────────────────────────────────────────

    /**
     * L'écran web `BillingCenter` renvoie des zéros codés en dur — « Données simulées, à connecter
     * à Invoice model ». Le recopier aurait donné un écran natif incapable d'afficher quoi que ce
     * soit. La donnée existe pourtant : `/api/client/invoices` la sert déjà.
     *
     * On passe donc par `ClientFinanceDocumentScope`, et pas par un `where` maison. Ce n'est pas
     * une commodité : le scope porte la restriction PAR LOCAL (`site_scope = 'selected'`) d'un
     * membre qui n'a accès qu'à certains sites. Un filtre écrit à la main ici aurait rendu à ce
     * membre la facturation de toute la société — une fuite à l'intérieur même de l'organisation.
     *
     * Le `where` sur l'organisation vient EN PLUS, jamais à la place : il retire de cet écran les
     * factures personnelles du membre, qui n'ont rien à y faire.
     */
    public function billing(): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('finance.view', $org);

        $portee = fn () => ClientFinanceDocumentScope::apply(FinanceInvoice::query(), Auth::user())
            ->where('organization_account_id', $org->id);

        /**
         * `ClientFinanceDocumentScope::apply()` est typée `Builder` sans générique : ce qui en sort
         * est un `Collection<Model>` pour l'analyse statique, alors que la requête part bien de
         * `FinanceInvoice`. On le rétablit ici plutôt que d'élargir la signature partagée, que
         * `FinanceDocumentsClient` et l'API factures utilisent aussi.
         *
         * @var Collection<int, FinanceInvoice> $lignes
         */
        $lignes = $portee()
            ->orderByDesc('issued_at')
            ->limit(50)
            ->get();

        $factures = $lignes
            ->map(fn (FinanceInvoice $f) => [
                'id' => $f->id,
                'invoice_number' => $f->invoice_number,
                'status' => $f->status,
                'currency' => $f->currency,
                'total_amount' => (float) $f->total_amount,
                'balance_due' => (float) $f->balance_due,
                'issued_at' => $f->issued_at?->toDateString(),
                'due_at' => $f->due_at?->toDateString(),
            ]);

        return response()->json([
            'data' => [
                'summary' => [
                    'unpaid' => (float) $portee()->where('status', '!=', 'paid')->sum('balance_due'),
                    'total_month' => (float) $portee()
                        ->whereBetween('issued_at', [now()->startOfMonth(), now()->endOfMonth()])
                        ->sum('total_amount'),
                    'count_total' => $portee()->count(),
                ],
                'invoices' => $factures,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Gardes communes
    // ──────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function presenteReservation(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'reference' => $booking->reference ?? null,
            'status' => $booking->status,
            'site' => $booking->organizationSite?->name,
            'provider' => $booking->providerUser?->name,
            'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
            /*
             * `bookings` ne porte PAS de colonne `total_amount` — j'avais écrit ce nom, et l'écran
             * aurait affiché un montant vide indéfiniment sans qu'aucune erreur ne le signale.
             * PHPStan l'a rattrapé. Le modèle documente `estimated_price` ; `final_price` existe en
             * base mais n'est ni castée ni déclarée sur le modèle, donc on ne l'invente pas ici.
             *
             * La lecture passe par `getAttributeValue` et non par la propriété : le modèle annote
             * `@property string $estimated_price`, non nullable, alors que la COLONNE l'est. Lue
             * par la propriété, la garde ci-dessous serait tenue pour toujours vraie — donc morte —
             * alors qu'elle protège un cas réel : un devis pas encore chiffré.
             */
            'estimated_price' => ($prix = $booking->getAttributeValue('estimated_price')) !== null
                ? (float) $prix
                : null,
        ];
    }

    private function exige(string $permission, OrganizationAccount $organisation): void
    {
        abort_unless(
            app(PermissionService::class)->can(Auth::user(), $permission, $organisation),
            403
        );
    }
}
