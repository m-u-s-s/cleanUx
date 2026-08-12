<?php

namespace App\Http\Controllers\Api\Provider;

use App\Enums\OrganizationRole;
use App\Events\CallStarted;
use App\Http\Controllers\Controller;
use App\Jobs\Calls\CloreLAppelNonRepondu;
use App\Jobs\Missions\AutoAssignerMissionsJob;
use App\Models\Call;
use App\Models\Channel;
use App\Models\FieldTeam;
use App\Models\FieldTeamMember;
use App\Models\Message;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\OrganizationRolePermission;
use App\Models\OrganizationSite;
use App\Models\ProviderAgency;
use App\Models\ProviderSiteAssignment;
use App\Models\ProviderSiteTeam;
use App\Models\Task;
use App\Models\User;
use App\Services\Calls\CallService;
use App\Services\Client\Calendar\BookingRescheduleService;
use App\Services\Messaging\AttachmentUploadService;
use App\Services\Messaging\ChannelManagementService;
use App\Services\Messaging\MessageService;
use App\Services\Missions\MissionAssignmentService;
use App\Services\Missions\ReassignmentPolicy;
use App\Services\Missions\WorkerAvailabilityService;
use App\Services\Organizations\OrganizationMemberAdministration;
use App\Services\Organizations\OrganizationNotifier;
use App\Services\Organizations\ResultatAdministration;
use App\Services\PermissionService;
use App\Services\Tasks\TaskVisibilityService;
use App\Support\Organizations\ResolvesActiveOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * L'API DE L'ESPACE SOCIÉTÉ PRESTATAIRE.
 *
 * `routes/api/provider.php` couvrait abondamment le prestataire INDIVIDUEL — missions,
 * disponibilités, badges, litiges, portefeuille — et rien de la société. Les écrans société étaient
 * donc servis en WebView faute de données à consommer côté natif.
 *
 * Deux règles gouvernent chaque méthode, sans exception :
 *   1. toute requête est limitée à l'organisation ACTIVE de l'appelant ;
 *   2. toute écriture exige une permission, jamais la seule appartenance.
 *
 * Les identifiants reçus ne sont jamais tenus pour fiables : le scoping fait partie de la REQUÊTE,
 * si bien qu'une ressource d'une autre société n'est jamais chargée — donc jamais divulguée, même
 * par la différence entre un 403 et un 404.
 */
class CompanyController extends Controller
{
    /*
     * La résolution de l'organisation vit dans un trait PARTAGÉ avec le contrôleur société cliente.
     *
     * Elle lisait ici `$user->currentOrganization`, donc la seule colonne `current_organization_id`
     * — que `db:seed` ne renseigne jamais. Les cinq écrans société répondaient 403 à tout compte
     * de démonstration, y compris après qu'on a rouvert leur porte d'entrée dans le profil.
     */
    use ResolvesActiveOrganization;

    // ──────────────────────────────────────────────────────
    // Accueil
    // ──────────────────────────────────────────────────────

    /**
     * Le résumé de la journée, en UN appel.
     *
     * L'écran d'accueil natif reconstituait ces chiffres à partir des autres points — quatre
     * requêtes pour cinq nombres, et autant d'occasions de dériver de ce que l'écran web affiche.
     *
     * `missions_today` compte les missions PLANIFIÉES aujourd'hui, pas celles créées aujourd'hui :
     * c'est la charge du jour que regarde un gérant en ouvrant son application, pas son carnet de
     * commandes.
     */
    public function overview(): JsonResponse
    {
        $org = $this->organisationActive();

        $missions = fn () => Mission::query()->where('provider_organization_id', $org->id);

        return response()->json([
            'data' => [
                'organization' => ['id' => $org->id, 'name' => $org->name],
                'kpis' => [
                    'missions_today' => $missions()->whereDate('planned_start_at', today())->count(),
                    'missions_active' => $missions()->whereIn('status', ['dispatched', 'in_progress'])->count(),
                    // « En retard » se mesure sur le PLANIFIÉ contre l'heure courante, pas sur un
                    // statut : une mission qu'on a oublié de démarrer est en retard sans qu'aucune
                    // colonne ne le dise.
                    'missions_delayed' => $missions()
                        ->where('status', '!=', 'completed')
                        ->where('planned_start_at', '<', now())
                        ->count(),
                    'missions_unassigned' => $missions()
                        ->whereNull('lead_provider_user_id')
                        ->whereDate('planned_start_at', today())
                        ->count(),
                    'members_active' => OrganizationMember::query()
                        ->where('organization_account_id', $org->id)
                        ->where('status', 'active')
                        ->count(),
                    'open_tasks' => Task::query()
                        ->where('organization_account_id', $org->id)
                        ->whereNotIn('status', ['done', 'cancelled'])
                        ->count(),
                ],
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Sites desservis
    // ──────────────────────────────────────────────────────

    /**
     * Les sites clients que la société dessert, et le référent qu'elle y place.
     *
     * Mêmes deux sources que l'écran web `SiteOperations` — missions et contrats-cadres — parce que
     * les sites se DÉDUISENT : un prestataire ne possède pas les locaux de ses clients.
     *
     * Les référents sont scopés sur notre organisation : deux prestataires peuvent desservir le
     * même immeuble, et la composition de l'équipe adverse ne nous regarde pas.
     */
    public function sites(): JsonResponse
    {
        $org = $this->organisationActive();

        $parMissions = Mission::query()
            ->where('provider_organization_id', $org->id)
            ->whereNotNull('organization_site_id')
            ->distinct()
            ->pluck('organization_site_id');

        $orgsSousContrat = OrganizationContract::query()
            ->where('provider_organization_id', $org->id)
            ->distinct()
            ->pluck('organization_account_id');

        $parContrats = OrganizationSite::query()
            ->whereIn('organization_account_id', $orgsSousContrat)
            ->pluck('id');

        $sites = OrganizationSite::query()
            ->whereIn('id', $parMissions->merge($parContrats)->unique())
            ->with([
                'providerAssignments' => fn ($q) => $q
                    ->where('provider_organization_id', $org->id)
                    ->with('user:id,name'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (OrganizationSite $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'city' => $s->city,
                'postal_code' => $s->postal_code,
                'address' => $s->address,
                'referents' => $s->providerAssignments
                    ->map(fn (ProviderSiteAssignment $a) => [
                        'id' => $a->id,
                        'name' => $a->user?->name,
                        'role' => $a->role,
                    ])
                    ->values()
                    ->all(),
            ]);

        return response()->json(['data' => $sites]);
    }

    /**
     * Nommer un référent sur un site desservi.
     *
     * `provider_site_assignments` existait depuis le 2026-08-07 avec ZÉRO ligne et AUCUN écrivain :
     * la table était prête, la connaissance qu'elle devait porter — qui connaît le code de la porte,
     * l'ascenseur en panne — n'avait aucun moyen d'y entrer.
     */
    public function assignSiteReferent(Request $request, int $siteId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('sites.assign_members', $org);

        $donnees = $request->validate([
            'user_id' => ['required', 'integer'],
            'role' => ['nullable', Rule::in([ProviderSiteAssignment::ROLE_LEAD, ProviderSiteAssignment::ROLE_BACKUP])],
        ]);

        abort_unless($this->desservonsNousCeSite($org->id, $siteId), 404);

        // Membre ACTIF de cette société : sans quoi on nommerait l'employé d'un concurrent.
        $membre = OrganizationMember::query()
            ->where('organization_account_id', $org->id)
            ->where('user_id', $donnees['user_id'])
            ->where('status', 'active')
            ->firstOrFail();

        $ligne = ProviderSiteAssignment::updateOrCreate(
            [
                'provider_organization_id' => $org->id,
                'organization_site_id' => $siteId,
                'user_id' => $membre->user_id,
            ],
            ['role' => $donnees['role'] ?? ProviderSiteAssignment::ROLE_LEAD],
        );

        return response()->json(['data' => ['id' => $ligne->id, 'role' => $ligne->role]], 201);
    }

    public function removeSiteReferent(int $siteId, int $userId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('sites.assign_members', $org);

        // Scoping DANS la requête : l'affectation d'un concurrent sur le même immeuble n'est jamais
        // touchée, ni même chargée.
        ProviderSiteAssignment::query()
            ->where('provider_organization_id', $org->id)
            ->where('organization_site_id', $siteId)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * L'ÉQUIPE HABITUELLE D'UN SITE.
     *
     * Nommer des PERSONNES ne suffit pas sur un grand immeuble : c'est une équipe entière qui y va,
     * et la désigner personne par personne recommence à chaque changement d'effectif.
     */
    public function setSiteDefaultTeam(Request $request, int $siteId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('sites.assign_members', $org);

        $donnees = $request->validate([
            'field_team_id' => ['nullable', 'integer'],
        ]);

        abort_unless($this->desservonsNousCeSite($org->id, $siteId), 404);

        // `null` retire l'équipe par défaut : un site peut cesser d'en avoir une.
        if (($donnees['field_team_id'] ?? null) === null) {
            ProviderSiteTeam::query()
                ->where('provider_organization_id', $org->id)
                ->where('organization_site_id', $siteId)
                ->delete();

            return response()->json(['data' => ['field_team_id' => null]]);
        }

        $equipe = FieldTeam::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($donnees['field_team_id']);

        ProviderSiteTeam::updateOrCreate(
            ['provider_organization_id' => $org->id, 'organization_site_id' => $siteId],
            ['field_team_id' => $equipe->id],
        );

        return response()->json(['data' => ['field_team_id' => $equipe->id]]);
    }

    /**
     * Desservons-nous ce site ?
     *
     * Même déduction que la liste des sites — missions et contrats-cadres. Un prestataire ne possède
     * pas les locaux de ses clients : il ne peut donc y nommer quelqu'un que s'il y intervient
     * réellement.
     */
    private function desservonsNousCeSite(int $organisationId, int $siteId): bool
    {
        $parMission = Mission::query()
            ->where('provider_organization_id', $organisationId)
            ->where('organization_site_id', $siteId)
            ->exists();

        if ($parMission) {
            return true;
        }

        $orgsSousContrat = OrganizationContract::query()
            ->where('provider_organization_id', $organisationId)
            ->distinct()
            ->pluck('organization_account_id');

        return OrganizationSite::query()
            ->whereKey($siteId)
            ->whereIn('organization_account_id', $orgsSousContrat)
            ->exists();
    }

    // ──────────────────────────────────────────────────────
    // Agences — les implantations de la SOCIÉTÉ
    // ──────────────────────────────────────────────────────

    /**
     * Les implantations de la société.
     *
     * À NE PAS CONFONDRE AVEC `organization_sites`, qui désigne les locaux du CLIENT. Les deux se
     * ressemblent sur le papier — une adresse, une ville — et n'ont rien à voir : une société
     * multi-villes n'avait aucun moyen de déclarer son dépôt de Bruxelles.
     */
    public function agencies(): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('agencies.view', $org);

        $agences = ProviderAgency::query()
            ->where('provider_organization_id', $org->id)
            ->orderBy('name')
            ->get()
            ->map(fn (ProviderAgency $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'city' => $a->city,
                'address' => $a->address,
                'status' => $a->status,
                'service_zone_id' => $a->service_zone_id,
            ]);

        return response()->json(['data' => $agences]);
    }

    public function createAgency(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('agencies.manage', $org);

        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'service_zone_id' => ['nullable', 'integer'],
        ]);

        $agence = ProviderAgency::create([
            'provider_organization_id' => $org->id,
            'name' => $donnees['name'],
            // Unique PAR SOCIÉTÉ : deux prestataires peuvent appeler leur implantation « nord ».
            'slug' => Str::slug($donnees['name']).'-'.Str::lower(Str::random(5)),
            'address' => $donnees['address'] ?? null,
            'city' => $donnees['city'] ?? null,
            'postal_code' => $donnees['postal_code'] ?? null,
            'service_zone_id' => $donnees['service_zone_id'] ?? null,
            'status' => 'active',
        ]);

        return response()->json(['data' => ['id' => $agence->id, 'name' => $agence->name]], 201);
    }

    public function updateAgency(Request $request, int $agencyId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('agencies.manage', $org);

        $agence = ProviderAgency::query()
            ->where('provider_organization_id', $org->id)
            ->findOrFail($agencyId);

        $donnees = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'service_zone_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
        ]);

        $agence->update(array_filter($donnees, fn ($v) => $v !== null));

        return response()->json(['data' => ['id' => $agence->id, 'status' => $agence->fresh()->status]]);
    }

    /**
     * Rattacher une équipe ou un membre à une agence.
     *
     * `null` détache — une société peut réorganiser, et un rattachement qu'on ne pourrait pas défaire
     * obligerait à recréer l'équipe.
     */
    public function attachToAgency(Request $request, int $agencyId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('agencies.manage', $org);

        $donnees = $request->validate([
            'field_team_id' => ['nullable', 'integer'],
            'member_id' => ['nullable', 'integer'],
            'detach' => ['nullable', 'boolean'],
        ]);

        $agence = ProviderAgency::query()
            ->where('provider_organization_id', $org->id)
            ->findOrFail($agencyId);

        $valeur = ($donnees['detach'] ?? false) ? null : $agence->id;

        if (($donnees['field_team_id'] ?? null) !== null) {
            FieldTeam::query()
                ->where('organization_account_id', $org->id)
                ->whereKey($donnees['field_team_id'])
                ->update(['provider_agency_id' => $valeur]);
        }

        if (($donnees['member_id'] ?? null) !== null) {
            OrganizationMember::query()
                ->where('organization_account_id', $org->id)
                ->whereKey($donnees['member_id'])
                ->update(['provider_agency_id' => $valeur]);
        }

        return response()->json(['data' => ['ok' => true, 'provider_agency_id' => $valeur]]);
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
                // `role` est CASTÉ en enum par le modèle : la normalisation défensive que j'avais
                // écrite ici était morte. Même correction qu'en phase 2 sur `PermissionService`.
                'role' => $m->role->value,
                'status' => $m->status,
                'joined_at' => $m->joined_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $membres]);
    }

    // ──────────────────────────────────────────────────────
    // Administration des membres
    // ──────────────────────────────────────────────────────

    /**
     * Changer le sous-rôle d'un membre depuis le téléphone.
     *
     * « L'owner change les sous-rôles de ses employés quand il veut », y compris en déplacement :
     * `CompanyMembersScreen` était en LECTURE SEULE, et l'écran web supposait un poste de travail.
     *
     * Les six règles — isolation, permission, hiérarchie, plafond de promotion, dernier
     * propriétaire, auto-action — viennent de `OrganizationMemberAdministration`, partagé avec
     * l'écran web. Les réécrire ici aurait produit deux jeux de garde-fous, et ce dépôt sait ce que
     * cela donne : l'écran client et l'écran prestataire avaient chacun une protection que l'autre
     * n'avait pas.
     */
    public function updateMemberRole(Request $request, int $memberId): JsonResponse
    {
        $org = $this->organisationActive();

        $donnees = $request->validate([
            'role' => [
                'required',
                'string',
                // Un rôle inconnu atteindrait `OrganizationRole::from()`, qui lève un `ValueError` :
                // 500 sur une saisie utilisateur. Même correction que sur l'invitation au lot 1.
                Rule::in(array_map(
                    fn (OrganizationRole $r) => $r->value,
                    OrganizationRole::forProviderCompany(),
                )),
            ],
        ]);

        $resultat = app(OrganizationMemberAdministration::class)
            ->changerLeRole(Auth::user(), $org->id, $memberId, $donnees['role']);

        return $this->reponseAdministration($resultat, fn (OrganizationMember $m) => [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'role' => $m->role->value,
            'status' => $m->status,
        ]);
    }

    public function suspendMember(int $memberId): JsonResponse
    {
        return $this->transitionDeStatut($memberId, 'suspended', 'members.suspend');
    }

    public function reactivateMember(int $memberId): JsonResponse
    {
        return $this->transitionDeStatut($memberId, 'active', 'members.suspend');
    }

    /**
     * Retirer un membre.
     *
     * Ce n'est PAS qu'un changement de statut : le service libère aussi les missions à venir et les
     * canaux d'équipe. Un endpoint qui se contenterait d'écrire `left` laisserait le répartiteur
     * croire ses missions couvertes, et l'ancien salarié dans les canaux Reverb de la société.
     */
    public function removeMember(int $memberId): JsonResponse
    {
        return $this->transitionDeStatut($memberId, 'left', 'members.remove');
    }

    private function transitionDeStatut(int $memberId, string $statut, string $permission): JsonResponse
    {
        $org = $this->organisationActive();

        $resultat = app(OrganizationMemberAdministration::class)
            ->changerLeStatut(Auth::user(), $org->id, $memberId, $statut, $permission);

        return $this->reponseAdministration($resultat, fn (OrganizationMember $m) => [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'status' => $m->status,
        ]);
    }

    /**
     * Traduire une décision du service en réponse HTTP.
     *
     * LE MOTIF PORTE SON PROPRE CODE. Un refus d'autorisation (403) et une règle de gestion (422)
     * ne se confondent pas : le dernier propriétaire ne se retire pas, mais celui qui essaie AVAIT
     * le droit — répondre 403 l'enverrait chercher une permission qu'il possède déjà.
     *
     * @param  callable(OrganizationMember): array<string, mixed>  $serialiser
     */
    private function reponseAdministration(ResultatAdministration $resultat, callable $serialiser): JsonResponse
    {
        if (! $resultat->applique) {
            $motif = $resultat->motif;

            return response()->json([
                'ok' => false,
                'reason' => $motif?->value,
                'message' => $motif?->message(),
            ], $motif?->codeHttp() ?? 403);
        }

        /** @var OrganizationMember $membre */
        $membre = $resultat->membre;

        return response()->json(['data' => $serialiser($membre)]);
    }

    // ──────────────────────────────────────────────────────
    // Matrice rôle → permissions de la société
    // ──────────────────────────────────────────────────────

    /**
     * L'état EFFECTIF de la matrice : réglage de la société s'il existe, défaut du code sinon.
     *
     * Le téléphone ne reconstitue rien — il n'a pas la matrice par défaut et ne doit pas l'avoir.
     */
    public function rolePermissions(): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('members.manage_permissions', $org);

        $permissions = app(PermissionService::class);

        $reglages = OrganizationRolePermission::query()
            ->where('organization_account_id', $org->id)
            ->get()
            ->groupBy('role');

        $matrice = [];

        foreach (OrganizationRole::forProviderCompany() as $role) {
            if ($role === OrganizationRole::OWNER) {
                continue; // Hors matrice : il porte la clé qui ouvre cet écran.
            }

            $parRole = $reglages->get($role->value, collect())->pluck('granted', 'permission');

            foreach ($permissions->allPermissionKeys() as $cle) {
                $matrice[$role->value][$cle] = $parRole->has($cle)
                    ? (bool) $parRole->get($cle)
                    : $permissions->roleAccordeParDefaut($role->value, $cle);
            }
        }

        return response()->json(['data' => [
            'permissions' => $permissions->allPermissionKeys(),
            'roles' => array_values(array_map(
                fn (OrganizationRole $r) => ['value' => $r->value, 'label' => $r->label()],
                array_filter(
                    OrganizationRole::forProviderCompany(),
                    fn (OrganizationRole $r) => $r !== OrganizationRole::OWNER,
                ),
            )),
            'matrix' => $matrice,
        ]]);
    }

    /**
     * Régler une case de la matrice.
     *
     * `granted` est un booléen EXPLICITE : sans lui la matrice ne saurait qu'élargir, et une société
     * ne pourrait jamais retirer un droit que le code accorde par défaut.
     */
    public function updateRolePermission(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('members.manage_permissions', $org);

        $donnees = $request->validate([
            'role' => [
                'required',
                'string',
                // Le propriétaire est exclu : lui retirer `members.manage_permissions` fermerait
                // cet écran à tout le monde, sans recours autre qu'une écriture en base.
                Rule::in(array_values(array_map(
                    fn (OrganizationRole $r) => $r->value,
                    array_filter(
                        OrganizationRole::forProviderCompany(),
                        fn (OrganizationRole $r) => $r !== OrganizationRole::OWNER,
                    ),
                ))),
            ],
            'permission' => ['required', 'string', Rule::in(app(PermissionService::class)->allPermissionKeys())],
            'granted' => ['required', 'boolean'],
        ]);

        OrganizationRolePermission::updateOrCreate(
            [
                'organization_account_id' => $org->id,
                'role' => $donnees['role'],
                'permission' => $donnees['permission'],
            ],
            ['granted' => $donnees['granted']],
        );

        // Purger toute l'organisation : ce réglage change les droits de plusieurs personnes d'un
        // coup, et les autres resteraient une minute sur l'ancienne réponse.
        app(PermissionService::class)->invalidateOrganizationCache($org->id);

        return response()->json(['data' => [
            'role' => $donnees['role'],
            'permission' => $donnees['permission'],
            'granted' => $donnees['granted'],
        ]]);
    }

    // ──────────────────────────────────────────────────────
    // Équipes terrain
    // ──────────────────────────────────────────────────────

    public function fieldTeams(): JsonResponse
    {
        $org = $this->organisationActive();

        $equipes = FieldTeam::query()
            ->where('organization_account_id', $org->id)
            ->with(['serviceZone:id,name', 'teamLead:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (FieldTeam $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'status' => $e->status,
                'zone' => $e->serviceZone?->name,
                'lead' => $e->teamLead?->name,
                'max_concurrent_missions' => $e->max_concurrent_missions,
            ]);

        return response()->json(['data' => $equipes]);
    }

    public function createFieldTeam(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('team.create', $org);

        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'max_concurrent_missions' => ['nullable', 'integer', 'min:1', 'max:50'],
            'service_zone_id' => ['nullable', 'integer'],
        ]);

        $equipe = FieldTeam::create([
            'organization_account_id' => $org->id,
            'name' => $donnees['name'],
            'slug' => Str::slug($donnees['name']).'-'.Str::lower(Str::random(5)),
            'status' => 'active',
            'service_zone_id' => $donnees['service_zone_id'] ?? null,
            'max_concurrent_missions' => $donnees['max_concurrent_missions'] ?? 3,
        ]);

        return response()->json(['data' => ['id' => $equipe->id, 'name' => $equipe->name]], 201);
    }

    public function archiveFieldTeam(int $teamId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('team.manage', $org);

        // Scoping DANS la requête : une équipe d'une autre société n'est jamais chargée.
        $equipe = FieldTeam::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($teamId);

        $equipe->update(['status' => 'archived']);

        return response()->json(['data' => ['id' => $equipe->id, 'status' => 'archived']]);
    }

    // ──────────────────────────────────────────────────────
    // Tâches
    // ──────────────────────────────────────────────────────

    /**
     * Le tableau des tâches, borné à ce que l'appelant a le droit de voir.
     *
     * PAS DE MIDDLEWARE ICI, ET C'EST VOLONTAIRE. Les quatre lectures de pilotage se refusent en
     * bloc à qui n'a pas la clé ; les tâches, non — un nettoyeur a de vraies tâches à consulter.
     * La garde n'est donc pas à l'entrée mais dans la requête, et la règle est celle de l'écran web,
     * au même endroit : voir `TaskVisibilityService`.
     */
    public function tasks(): JsonResponse
    {
        $org = $this->organisationActive();

        $taches = app(TaskVisibilityService::class)
            ->requetePour(Auth::user(), $org->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'status' => $t->status,
                'priority' => $t->priority,
                'due_date' => $t->due_date?->toDateString(),
            ]);

        return response()->json(['data' => $taches]);
    }

    public function createTask(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('tasks.create', $org);

        $donnees = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
        ]);

        $tache = Task::create([
            'organization_account_id' => $org->id,
            'created_by' => Auth::id(),
            'title' => $donnees['title'],
            'description' => $donnees['description'] ?? null,
            'priority' => $donnees['priority'],
            'status' => Task::STATUS_TODO,
        ]);

        return response()->json(['data' => ['id' => $tache->id, 'title' => $tache->title]], 201);
    }

    public function updateTask(Request $request, int $taskId): JsonResponse
    {
        $org = $this->organisationActive();

        $tache = Task::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($taskId);

        /*
         * Déplacer une tâche est une écriture : un rôle en lecture seule ne doit pas marquer
         * terminé le travail des autres. Le créateur garde la main sur la sienne — même règle que
         * l'écran web, pour que les deux surfaces ne divergent pas.
         */
        abort_unless(
            $tache->created_by === Auth::id()
                || app(PermissionService::class)->can(Auth::user(), 'tasks.create', $org),
            403
        );

        /*
         * PUIS : cette tâche figure-t-elle sur SON tableau ? `tasks.create` est accordée jusqu'au
         * nettoyeur, si bien qu'elle laissait déplacer la tâche d'un collègue qu'on n'a pas le droit
         * de lire, en devinant un identifiant. Deux questions, deux réponses — le refus d'écrire
         * reste un 403, l'absence du tableau un 404 qui n'apprend rien.
         */
        abort_unless(
            app(TaskVisibilityService::class)
                ->requetePour(Auth::user(), $org->id)
                ->whereKey($taskId)
                ->exists(),
            404
        );

        $donnees = $request->validate([
            'status' => ['required', 'in:todo,in_progress,done,cancelled'],
        ]);

        $tache->update(['status' => $donnees['status']]);

        return response()->json(['data' => ['id' => $tache->id, 'status' => $tache->status]]);
    }

    // ──────────────────────────────────────────────────────
    // Répartition
    // ──────────────────────────────────────────────────────

    public function missions(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('missions.dispatch', $org);

        $missions = Mission::query()
            ->where('provider_organization_id', $org->id)
            ->when($request->query('date'), fn ($q, $d) => $q->whereDate('planned_start_at', $d))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->with(['leadProvider:id,name', 'bookingSite:id,name,city'])
            ->orderBy('planned_start_at')
            ->limit(100)
            ->get()
            ->map(fn (Mission $m) => [
                'id' => $m->id,
                'status' => $m->status,
                'planned_start_at' => $m->planned_start_at?->toIso8601String(),
                'site' => $m->bookingSite?->name,
                'city' => $m->bookingSite?->city,
                'lead' => $m->leadProvider?->name,
                'lead_user_id' => $m->lead_provider_user_id,
            ]);

        return response()->json(['data' => $missions]);
    }

    /**
     * Assigner ou RÉASSIGNER une mission à une personne.
     *
     * LA GARDE N'EST PLUS `missions.dispatch` SEULE. Un chef d'équipe doit pouvoir échanger deux de
     * ses membres sans porter la clé qui ouvre le dispatch de toute la société — c'est l'exigence 5,
     * et sa PORTÉE (« son équipe seulement ») n'est pas exprimable dans une matrice de clés. Voir
     * `ReassignmentPolicy`, consommée à l'identique par le web.
     */
    public function assignMission(Request $request, int $missionId): JsonResponse
    {
        $org = $this->organisationActive();

        $donnees = $request->validate([
            'user_id' => ['required', 'integer'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        $mission = Mission::query()
            ->where('provider_organization_id', $org->id)
            ->findOrFail($missionId);

        abort_unless(
            app(ReassignmentPolicy::class)->peutReassigner(Auth::user(), $mission),
            403
        );

        // L'identifiant du travailleur vient du client : il doit désigner un membre ACTIF de cette
        // société, faute de quoi on assignerait une mission à l'employé d'une autre entreprise.
        $travailleur = OrganizationMember::query()
            ->where('organization_account_id', $org->id)
            ->where('user_id', $donnees['user_id'])
            ->where('status', 'active')
            ->firstOrFail();

        // Règle partagée avec l'écran web : libère les autres leads actifs puis synchronise
        // `lead_provider_user_id`. Voir `MissionAssignmentService`.
        app(MissionAssignmentService::class)->assigner(
            $mission,
            $travailleur,
            Auth::id(),
            $donnees['motif'] ?? null,
        );

        return response()->json(['data' => [
            'id' => $mission->id,
            'lead_user_id' => $travailleur->user_id,
        ]]);
    }

    /**
     * Confier la mission à une ÉQUIPE entière.
     *
     * On n'envoie pas une personne dans un immeuble de dix étages, on y envoie l'équipe Nord. Le
     * geste n'existait sur aucune surface : composer une équipe demandait un responsable puis N
     * renforts, un par un, sans jamais dire QUELLE équipe.
     */
    public function assignMissionToTeam(Request $request, int $missionId): JsonResponse
    {
        $org = $this->organisationActive();

        $donnees = $request->validate([
            'field_team_id' => ['required', 'integer'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        $mission = Mission::query()
            ->where('provider_organization_id', $org->id)
            ->findOrFail($missionId);

        abort_unless(
            app(ReassignmentPolicy::class)->peutReassigner(Auth::user(), $mission),
            403
        );

        // Scoping DANS la requête : l'équipe d'une autre société n'est jamais chargée.
        $equipe = FieldTeam::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($donnees['field_team_id']);

        $applique = app(MissionAssignmentService::class)->assignerEquipe(
            $mission,
            $equipe,
            Auth::id(),
            $donnees['motif'] ?? null,
        );

        // Une équipe sans membre actif ne peut rien exécuter : 422, parce que l'acteur avait le
        // droit — c'est l'état de l'équipe qui s'y oppose.
        abort_unless($applique, 422, "Cette équipe n'a aucun membre actif dans la société.");

        $mission->refresh();

        return response()->json(['data' => [
            'id' => $mission->id,
            'field_team_id' => $mission->field_team_id,
            'lead_user_id' => $mission->lead_provider_user_id,
        ]]);
    }

    /**
     * Ajouter ou retirer un RENFORT.
     *
     * Un grand nettoyage à deux est le cas ordinaire d'une société, et il n'était pas représentable
     * depuis le mobile : l'API ne savait qu'assigner une personne en remplaçant la précédente.
     *
     * Même garde que la réassignation — c'est la même redistribution de travail, seule la place
     * occupée diffère.
     */
    public function missionHelpers(Request $request, int $missionId): JsonResponse
    {
        $org = $this->organisationActive();

        $donnees = $request->validate([
            'user_id' => ['required', 'integer'],
            'remove' => ['nullable', 'boolean'],
        ]);

        $mission = Mission::query()
            ->where('provider_organization_id', $org->id)
            ->findOrFail($missionId);

        abort_unless(
            app(ReassignmentPolicy::class)->peutReassigner(Auth::user(), $mission),
            403
        );

        $service = app(MissionAssignmentService::class);

        if ($donnees['remove'] ?? false) {
            $service->retirerRenfort($mission, (int) $donnees['user_id'], Auth::id());

            return response()->json(['data' => ['ok' => true, 'removed' => true]]);
        }

        // Membre ACTIF de cette société : sans quoi on affecterait l'employé d'une autre entreprise.
        $renfort = OrganizationMember::query()
            ->where('organization_account_id', $org->id)
            ->where('user_id', $donnees['user_id'])
            ->where('status', 'active')
            ->firstOrFail();

        $service->ajouterRenfort($mission, $renfort);

        return response()->json(['data' => ['ok' => true, 'user_id' => $renfort->user_id]], 201);
    }

    /**
     * DÉPLACER UNE INTERVENTION — date, heure et LIEU.
     *
     * `BookingRescheduleService` était strictement client/admin, et aucun endpoint ne l'exposait au
     * prestataire : une société qui devait décaler d'une heure appelait le client pour qu'il le
     * fasse lui-même. Le LIEU, lui, ne bougeait jamais — la notion n'existait dans aucun chemin.
     *
     * L'application est immédiate et le client notifié systématiquement ; sous la fenêtre de gel
     * (24 h), seuls le propriétaire et le directeur d'opérations décident, avec motif obligatoire.
     */
    public function rescheduleMission(Request $request, int $missionId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('missions.reschedule', $org);

        $donnees = $request->validate([
            'date' => ['required', 'date'],
            'heure' => ['nullable', 'string', 'max:8'],
            'organization_site_id' => ['nullable', 'integer'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'motif' => ['nullable', 'string', 'max:500'],
        ]);

        $mission = Mission::query()
            ->where('provider_organization_id', $org->id)
            ->findOrFail($missionId);

        $rendezVous = $mission->booking;

        abort_if($rendezVous === null, 422, 'Cette mission n’a pas de rendez-vous à déplacer.');

        try {
            app(BookingRescheduleService::class)->reprogrammerParPrestataire(
                rendezVous: $rendezVous,
                acteur: Auth::user(),
                nouvelleDate: Carbon::parse($donnees['date']),
                nouvelleHeure: $donnees['heure'] ?? null,
                nouveauSiteId: $donnees['organization_site_id'] ?? null,
                nouvelleAdresse: $donnees['adresse'] ?? null,
                motif: $donnees['motif'] ?? null,
            );
        } catch (\DomainException $e) {
            /*
             * 422 ET NON 403. La fenêtre de gel et le site illégitime ne sont pas des refus
             * d'autorisation : l'acteur AVAIT le droit de déplacer, c'est cette demande-là qui ne
             * passe pas. Répondre 403 l'enverrait chercher une permission qu'il possède déjà.
             */
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $mission->refresh();

        return response()->json(['data' => [
            'id' => $mission->id,
            'planned_start_at' => $mission->planned_start_at?->toIso8601String(),
            'organization_site_id' => $mission->organization_site_id,
        ]]);
    }

    // ──────────────────────────────────────────────────────
    // Disponibilité et auto-assignation
    // ──────────────────────────────────────────────────────

    /**
     * Qui est libre sur le créneau d'une mission.
     *
     * L'écran mobile remplaçait un `Alert.alert` limité à dix noms, SANS indicateur de
     * disponibilité : le répartiteur choisissait à l'aveugle depuis son téléphone, là où l'écran web
     * le renseignait déjà.
     */
    public function availability(Request $request): JsonResponse
    {
        $org = $this->organisationActive();

        $donnees = $request->validate([
            'mission_id' => ['required', 'integer'],
        ]);

        $mission = Mission::query()
            ->where('provider_organization_id', $org->id)
            ->findOrFail($donnees['mission_id']);

        $debut = $mission->planned_start_at;

        // Sans horaire, la question n'a pas de sens : on ne prétend pas que tout le monde est libre.
        if ($debut === null) {
            return response()->json(['data' => ['mission_id' => $mission->id, 'workers' => []]]);
        }

        $verdicts = app(WorkerAvailabilityService::class)->libresPour(
            organisationId: $org->id,
            debut: $debut,
            fin: $mission->planned_end_at,
            exclureMissionId: $mission->id,
        );

        $noms = User::query()->whereIn('id', array_keys($verdicts))->pluck('name', 'id');

        $travailleurs = [];

        foreach ($verdicts as $userId => $libre) {
            $travailleurs[] = [
                'user_id' => $userId,
                'name' => $noms[$userId] ?? null,
                'is_free' => $libre,
            ];
        }

        return response()->json(['data' => [
            'mission_id' => $mission->id,
            'planned_start_at' => $debut->toIso8601String(),
            'workers' => $travailleurs,
        ]]);
    }

    /**
     * « Assigner tout ce qui n'a personne » — mis en FILE, pas exécuté ici.
     *
     * Deux cents missions, c'est deux cents décisions et autant de notifications : les traiter
     * pendant que le téléphone attend donnerait un écran figé puis un timeout, avec le travail à
     * moitié fait et rien pour dire où il s'est arrêté.
     */
    public function autoAssign(): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('missions.dispatch', $org);

        AutoAssignerMissionsJob::dispatch($org->id, Auth::id());

        return response()->json(['data' => ['ok' => true, 'queued' => true]], 202);
    }

    public function autoAssignSettings(): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('missions.dispatch', $org);

        return response()->json(['data' => [
            'auto_assign_enabled' => (bool) $org->auto_assign_enabled,
        ]]);
    }

    /**
     * Le MODE CONTINU — toute nouvelle mission de la société est auto-assignée.
     *
     * Réglage de SOCIÉTÉ, pas préférence d'écran : il agit sur des missions créées quand personne
     * n'est devant l'application. C'est aussi pourquoi il est faux par défaut.
     */
    public function updateAutoAssignSettings(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('missions.dispatch', $org);

        $donnees = $request->validate([
            'auto_assign_enabled' => ['required', 'boolean'],
        ]);

        $org->update(['auto_assign_enabled' => $donnees['auto_assign_enabled']]);

        return response()->json(['data' => [
            'auto_assign_enabled' => (bool) $org->fresh()->auto_assign_enabled,
        ]]);
    }

    // ──────────────────────────────────────────────────────
    // Composition des équipes terrain
    // ──────────────────────────────────────────────────────

    /**
     * Les membres d'une équipe — la composition, gérée PAR la société.
     *
     * `field_team_members` n'était manipulable que depuis l'administration de la plateforme : une
     * société qui créait son équipe sur son propre écran ne pouvait pas la peupler, et devait
     * appeler un administrateur pour y mettre quelqu'un.
     */
    public function fieldTeamMembers(int $teamId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('team.view', $org);

        $equipe = FieldTeam::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($teamId);

        $membres = FieldTeamMember::query()
            ->where('field_team_id', $equipe->id)
            ->where('is_active', true)
            ->whereNull('left_at')
            ->with('user:id,name,email')
            ->get()
            ->map(fn (FieldTeamMember $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'is_team_lead' => (int) $equipe->team_lead_user_id === (int) $m->user_id,
            ]);

        return response()->json(['data' => [
            'team' => ['id' => $equipe->id, 'name' => $equipe->name, 'status' => $equipe->status],
            'members' => $membres,
        ]]);
    }

    public function addFieldTeamMember(Request $request, int $teamId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('team.manage', $org);

        $donnees = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $equipe = FieldTeam::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($teamId);

        // La cible doit être un membre ACTIF de la société : sans cette garde, une équipe pourrait
        // enrôler l'employé d'une entreprise concurrente.
        $membreDeLaSociete = OrganizationMember::query()
            ->where('organization_account_id', $org->id)
            ->where('user_id', $donnees['user_id'])
            ->where('status', 'active')
            ->firstOrFail();

        /*
         * `updateOrCreate` sur (équipe, personne) : le geste est REJOUABLE. Quelqu'un qui avait
         * quitté l'équipe la réintègre par le même bouton, sans ligne en double — et `left_at` est
         * remis à null, sans quoi il resterait invisible des lectures.
         */
        $ligne = FieldTeamMember::updateOrCreate(
            ['field_team_id' => $equipe->id, 'user_id' => $membreDeLaSociete->user_id],
            ['is_active' => true, 'left_at' => null, 'joined_at' => now()],
        );

        return response()->json(['data' => [
            'id' => $ligne->id,
            'user_id' => $ligne->user_id,
        ]], 201);
    }

    /**
     * Retirer quelqu'un d'une équipe.
     *
     * La ligne SURVIT — `is_active` à faux et `left_at` daté. L'historique d'une équipe doit pouvoir
     * dire qui en a fait partie : les missions passées portent son nom, et une réclamation se règle
     * sur ce genre de détail.
     */
    public function removeFieldTeamMember(int $teamId, int $userId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('team.manage', $org);

        $equipe = FieldTeam::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($teamId);

        FieldTeamMember::query()
            ->where('field_team_id', $equipe->id)
            ->where('user_id', $userId)
            ->update(['is_active' => false, 'left_at' => now()]);

        /*
         * LE MENEUR QUI PART CESSE DE MENER.
         *
         * Laisser `team_lead_user_id` désigner un partant donnerait la mission au premier membre
         * actif à l'assignation suivante — sans que rien n'explique pourquoi — et
         * `ReassignmentPolicy` continuerait de lui accorder la main sur les missions de l'équipe.
         */
        if ((int) $equipe->team_lead_user_id === $userId) {
            $equipe->update(['team_lead_user_id' => null]);
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    // ──────────────────────────────────────────────────────
    // Canaux
    // ──────────────────────────────────────────────────────

    public function channels(): JsonResponse
    {
        $org = $this->organisationActive();
        $utilisateur = Auth::user();

        // Mêmes bornes que la barre latérale web : les canaux de l'organisation dont on est membre.
        $canaux = Channel::query()
            ->where('organization_account_id', $org->id)
            ->whereHas('members', fn ($q) => $q->where('user_id', $utilisateur->id))
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_private'])
            ->map(fn (Channel $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'is_private' => (bool) $c->is_private,
            ]);

        return response()->json(['data' => $canaux]);
    }

    /**
     * Créer un canal depuis le mobile.
     *
     * Toute la gestion vivait dans `TeamChannels.php`, l'écran web : l'API ne savait que lister,
     * lire et poster. Une équipe sur le terrain pouvait donc RÉPONDRE, jamais ouvrir un fil.
     */
    public function createChannel(Request $request): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('channels.create', $org);

        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'type' => ['nullable', Rule::in(['team', 'mission', 'support', 'private', 'announcement'])],
            'is_private' => ['nullable', 'boolean'],
            'invite_whole_team' => ['nullable', 'boolean'],
        ]);

        $canal = app(ChannelManagementService::class)->creer(
            acteur: Auth::user(),
            organisationId: $org->id,
            nom: $donnees['name'],
            type: $donnees['type'] ?? 'team',
            prive: (bool) ($donnees['is_private'] ?? false),
            avecTouteLEquipe: (bool) ($donnees['invite_whole_team'] ?? false),
        );

        return response()->json(['data' => ['id' => $canal->id, 'name' => $canal->name]], 201);
    }

    /**
     * Ouvrir — ou retrouver — la conversation à deux avec un collègue.
     *
     * ON CHERCHE AVANT DE CRÉER : sans cela, chaque appui ajouterait un canal, et l'historique se
     * disperserait entre des fils vides reliant les deux mêmes personnes.
     */
    public function openDirectChannel(Request $request): JsonResponse
    {
        $org = $this->organisationActive();

        $donnees = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $canal = app(ChannelManagementService::class)
            ->ouvrirConversationDirecte(Auth::user(), $org->id, (int) $donnees['user_id']);

        abort_if($canal === null, 404, 'Ce collègue est introuvable dans votre société.');

        return response()->json(['data' => ['id' => $canal->id, 'name' => $canal->name]]);
    }

    public function channelMembers(int $channelId): JsonResponse
    {
        $canal = $this->canalSousGarde($channelId, 'view');

        $membres = $canal->members()
            ->get(['users.id', 'users.name'])
            ->map(fn (User $u) => [
                'user_id' => $u->id,
                'name' => $u->name,
                'role' => $u->pivot->role ?? 'member',
            ]);

        return response()->json(['data' => $membres]);
    }

    /**
     * Ajouter un participant — « en deux gestes », comme le demande l'exigence 4.
     *
     * La garde est celle du CANAL (`ChannelPolicy`), pas une clé d'organisation : c'est le
     * propriétaire ou un modérateur du fil qui décide qui y entre, et cette règle existait déjà.
     */
    public function addChannelMember(Request $request, int $channelId): JsonResponse
    {
        $canal = $this->canalSousGarde($channelId, 'manageMembers');

        $donnees = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $ajoute = app(ChannelManagementService::class)
            ->ajouterMembre($canal, (int) $donnees['user_id']);

        // On ne dit pas laquelle des deux conditions a manqué : la différence renseignerait sur
        // l'effectif d'une autre société.
        abort_unless($ajoute, 404, 'Cette personne n’est pas un membre actif de votre société.');

        return response()->json(['data' => ['ok' => true]], 201);
    }

    /**
     * Retirer un participant.
     *
     * RETIRER COUPE AUSSI LE TEMPS RÉEL : l'autorisation Reverb `channel.{id}` vérifie
     * l'appartenance à chaque abonnement.
     */
    public function removeChannelMember(int $channelId, int $userId): JsonResponse
    {
        $canal = $this->canalSousGarde($channelId, 'view');

        $cible = User::findOrFail($userId);

        // `kickMember` protège déjà le propriétaire du canal : on la consulte plutôt que de
        // réimplémenter cette règle.
        abort_unless(Auth::user()->can('kickMember', [$canal, $cible]), 403);

        app(ChannelManagementService::class)->retirerMembre($canal, $userId);

        return response()->json(['data' => ['ok' => true]]);
    }

    /** Quitter un canal soi-même — sans demander la permission à personne. */
    public function leaveChannel(int $channelId): JsonResponse
    {
        $canal = $this->canalSousGarde($channelId, 'view');

        app(ChannelManagementService::class)->retirerMembre($canal, (int) Auth::id());

        return response()->json(['data' => ['ok' => true]]);
    }

    public function markChannelRead(int $channelId): JsonResponse
    {
        $canal = $this->canalSousGarde($channelId, 'view');

        app(ChannelManagementService::class)->marquerCommeLu($canal, (int) Auth::id());

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * Les non-lus par canal.
     *
     * `channel_members.last_read_at` existait depuis l'origine et n'était écrit par personne : les
     * non-lus ne pouvaient donc pas exister, et la liste ne disait jamais où il se passait quelque
     * chose.
     */
    public function channelsUnreadCounts(): JsonResponse
    {
        $org = $this->organisationActive();

        return response()->json([
            'data' => app(ChannelManagementService::class)->nonLusPour($org->id, (int) Auth::id()),
        ]);
    }

    public function channelMessages(Request $request, int $channelId): JsonResponse
    {
        $canal = $this->canalSousGarde($channelId, 'view');

        $messages = Message::query()
            ->where('channel_id', $canal->id)
            ->topLevel()
            /*
             * PAGINATION PAR CURSEUR, pas par page.
             *
             * Un fil vivant reçoit des messages pendant qu'on le remonte : une pagination par
             * décalage rejouerait ou sauterait des lignes à chaque nouveau message. `before_id`
             * désigne un point fixe dans l'historique.
             */
            ->when(
                $request->query('before_id'),
                fn ($q, $avant) => $q->where('id', '<', (int) $avant)
            )
            ->with('sender:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'content' => $m->content,
                'sender' => $m->sender->name ?? 'Utilisateur supprimé',
                'sender_id' => $m->user_id,
                'is_system' => $m->type === Message::TYPE_SYSTEM,
                'sent_at' => $m->created_at?->toIso8601String(),
                /*
                 * LE TYPE ET L'ADRESSE DE LECTURE VOYAGENT — ils ne voyageaient pas.
                 *
                 * On pouvait ENVOYER une note vocale et personne ne pouvait l'écouter : la réponse
                 * ne disait ni que le message était vocal, ni où trouver le son. Le fil affichait
                 * « 🎙️ Note vocale » comme un texte ordinaire, sur mobile comme sur le web.
                 *
                 * L'adresse est signée et expire : une pièce jointe de messagerie d'équipe n'a pas
                 * à être lisible par quiconque devine son identifiant.
                 */
                'type' => $m->type,
                'duration' => data_get($m->metadata, 'duration'),
                'audio_url' => $m->type === Message::TYPE_VOICE
                    ? $this->adresseDeLecture($m)
                    : null,
            ]);

        return response()->json(['data' => $messages]);
    }

    /**
     * L'adresse signée où écouter la note vocale de ce message, ou `null` s'il n'y en a pas.
     *
     * Quinze minutes : le temps d'ouvrir le fil et d'appuyer, pas celui de faire circuler un lien.
     */
    protected function adresseDeLecture(Message $message): ?string
    {
        $piece = $message->attachments()->latest('id')->first();

        if (! $piece) {
            return null;
        }

        return URL::temporarySignedRoute(
            'messaging.attachments.download',
            now()->addMinutes(15),
            ['attachment' => $piece->id],
        );
    }

    public function postChannelMessage(Request $request, int $channelId): JsonResponse
    {
        // `postMessage` refuse aussi un canal verrouillé ou archivé — pas seulement un non-membre.
        $canal = $this->canalSousGarde($channelId, 'postMessage');

        $donnees = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        // `MessageService` gère mentions, notifications et diffusion en une transaction : on ne
        // réimplémente rien ici, sous peine de voir les deux surfaces diverger.
        app(MessageService::class)->send(
            channel: $canal,
            sender: Auth::user(),
            content: $donnees['content'],
        );

        return response()->json(['data' => ['ok' => true]], 201);
    }

    /**
     * ENVOYER UNE NOTE VOCALE.
     *
     * Sur un chantier, on ne tape pas : on a les mains prises, des gants, et le téléphone au fond
     * d'une poche. Une messagerie d'équipe terrain qui n'accepte que du texte se fait remplacer par
     * WhatsApp — hors de l'outil, hors de toute trace, et hors de la modération.
     *
     * LE FICHIER PASSE PAR LE MÊME CHEMIN QUE LES AUTRES PIÈCES JOINTES : même disque, même
     * plafond, même scan antivirus. Réécrire un stockage pour l'audio aurait créé une seconde porte,
     * qu'on aurait fini par oublier de garder.
     */
    public function sendVoiceNote(Request $request, int $channelId): JsonResponse
    {
        $canal = $this->canalSousGarde($channelId, 'postMessage');

        $donnees = $request->validate([
            /*
             * Types AUDIO uniquement, et liste blanche : `mimetypes` regarde le contenu réel, pas
             * l'extension. Un exécutable renommé `.m4a` ne passe pas.
             */
            'audio' => [
                'required',
                'file',
                'mimetypes:audio/mp4,audio/aac,audio/m4a,audio/x-m4a,audio/mpeg,audio/webm',
                'max:5120',
            ],
            'duration' => ['nullable', 'integer', 'min:1', 'max:600'],
        ]);

        /*
         * `MessageService::send()` porte mentions, notifications et diffusion en une transaction.
         * Le contenu textuel est un LIBELLÉ, pas une transcription : les clients qui ne savent pas
         * lire le type `voice` affichent au moins quelque chose d'intelligible.
         */
        $message = app(MessageService::class)->send(
            channel: $canal,
            sender: Auth::user(),
            content: '🎙️ Note vocale',
            type: Message::TYPE_VOICE,
            metadata: ['duration' => $donnees['duration'] ?? null],
        );

        /*
         * LE FICHIER PASSE PAR `AttachmentUploadService`, ET C'EST UNE CORRECTION.
         *
         * Le code stockait le fichier lui-même avec `store()` pendant que son commentaire promettait
         * « même scan antivirus ». C'était faux : `store()` ne déclenche rien. Une seconde porte
         * d'entrée de fichiers, sans analyse, sur une messagerie d'équipe — et le seul chemin par
         * lequel on pouvait ensuite RELIRE le fichier n'existait pas non plus, faute de pièce jointe
         * à désigner.
         *
         * En passant par le service, la note vocale hérite de tout : disque configuré, scan
         * antivirus asynchrone, refus de lecture si infecté, et la route de téléchargement signée qui
         * vérifie déjà l'appartenance au canal.
         */
        $piece = app(AttachmentUploadService::class)->attach(
            $message,
            Auth::user(),
            $request->file('audio'),
        );

        return response()->json(['data' => [
            'id' => $message->id,
            'type' => $message->type,
            'attachment_id' => $piece->id,
            'duration' => $donnees['duration'] ?? null,
        ]], 201);
    }

    // ──────────────────────────────────────────────────────
    // Appels audio / vidéo
    // ──────────────────────────────────────────────────────

    /**
     * OUVRIR UN APPEL DANS UN CANAL.
     *
     * La note vocale du lot 7 couvre la consigne qu'on laisse ; un appel couvre la question qui
     * n'attend pas — « je suis devant la porte, quel est le code ? ». Rien ne portait cela :
     * `VideoCallService` était un squelette qui levait sur chaque méthode ; il a été supprimé.
     *
     * LE JETON N'EST PAS DIFFUSÉ. La bannière part sur `channel.{id}` avec l'identifiant de
     * l'appel ; chacun demande ENSUITE le sien. Diffuser un jeton donnerait à tous les membres le
     * droit d'entrer dans la salle sans avoir décroché.
     */
    public function startCall(Request $request, int $channelId): JsonResponse
    {
        $canal = $this->canalSousGarde($channelId, 'postMessage');

        $donnees = $request->validate([
            'type' => ['nullable', Rule::in(['audio', 'video'])],
        ]);

        $service = app(CallService::class);

        /*
         * SANS CLÉ, ON NE PROPOSE RIEN — et on le dit. Un jeton signé avec un secret vide serait
         * rejeté par le serveur LiveKit : mieux vaut un refus explicite qu'un appel qui échoue à la
         * connexion, sans que personne comprenne pourquoi.
         */
        abort_unless($service->estConfigure(), 503, 'Les appels ne sont pas configurés sur cette instance.');

        $appel = $service->ouvrir($canal, Auth::user(), $donnees['type'] ?? 'audio');

        broadcast(new CallStarted($appel));

        /*
         * Le PUSH double la diffusion, et ce n'est pas redondant : un collègue dont l'application
         * est fermée ne reçoit rien de Reverb. C'est précisément le cas d'usage — on appelle
         * quelqu'un qui n'est pas devant son écran.
         */
        $this->prevenirLesAutresMembres($canal, $appel);

        // Le délai de sonnerie produit l'état MANQUÉ : sans lui, un appel que personne ne décroche
        // sonnerait pour toujours.
        CloreLAppelNonRepondu::dispatch($appel->id)
            ->delay(now()->addSeconds((int) config('livekit.ring_timeout_seconds', 45)));

        return response()->json(['data' => [
            'call_id' => $appel->id,
            'room_name' => $appel->room_name,
            'url' => config('livekit.url'),
            'token' => $service->jetonPour($appel, Auth::user()),
            'type' => $appel->type,
        ]], 201);
    }

    /** Le jeton de CETTE personne pour CET appel — chacun demande le sien. */
    public function callToken(int $callId): JsonResponse
    {
        $appel = $this->appelSousGarde($callId);

        abort_if(
            in_array($appel->status, [Call::STATUS_ENDED, Call::STATUS_MISSED], true),
            410,
            'Cet appel est terminé.',
        );

        $service = app(CallService::class);

        // Demander son jeton, c'est décrocher : c'est ce qui arrête la sonnerie côté appelant.
        if ((int) $appel->initiator_user_id !== (int) Auth::id()) {
            $service->repondre($appel);
        }

        return response()->json(['data' => [
            'call_id' => $appel->id,
            'room_name' => $appel->room_name,
            'url' => config('livekit.url'),
            'token' => $service->jetonPour($appel, Auth::user()),
        ]]);
    }

    public function endCall(int $callId): JsonResponse
    {
        $appel = $this->appelSousGarde($callId);

        app(CallService::class)->terminer($appel);

        return response()->json(['data' => ['status' => $appel->fresh()->status]]);
    }

    public function showCall(int $callId): JsonResponse
    {
        $appel = $this->appelSousGarde($callId);

        return response()->json(['data' => [
            'id' => $appel->id,
            'channel_id' => $appel->channel_id,
            'type' => $appel->type,
            'status' => $appel->status,
            'initiator_user_id' => $appel->initiator_user_id,
            'started_at' => $appel->started_at?->toIso8601String(),
            'ended_at' => $appel->ended_at?->toIso8601String(),
        ]]);
    }

    /**
     * Un appel dont on est membre du canal.
     *
     * Le scoping passe par le CANAL : `canalSousGarde()` vérifie à la fois l'organisation et
     * l'appartenance au fil. Un appel d'une autre société n'est donc jamais chargé.
     */
    private function appelSousGarde(int $callId): Call
    {
        $appel = Call::query()->findOrFail($callId);

        $this->canalSousGarde((int) $appel->channel_id, 'view');

        return $appel;
    }

    private function prevenirLesAutresMembres(Channel $canal, Call $appel): void
    {
        $notifier = app(OrganizationNotifier::class);
        // `Auth::user()` est typé `User` sur une route authentifiée : un repli ici serait mort.
        $appelant = Auth::user()->name;

        foreach ($canal->members()->pluck('users.id') as $membreId) {
            if ((int) $membreId === (int) Auth::id()) {
                continue;
            }

            $notifier->notifierUtilisateur(
                userId: (int) $membreId,
                titre: 'Appel entrant',
                corps: "{$appelant} vous appelle.",
                donnees: [
                    'type' => 'call_started',
                    'call_id' => $appel->id,
                    'channel_id' => $canal->id,
                ],
                cleIdempotence: "call:{$appel->id}:u{$membreId}",
            );
        }
    }

    /**
     * Un canal de l'organisation active, sur lequel l'appelant a l'autorisation demandée.
     *
     * Le scoping fait partie de la requête : un canal d'une autre société n'est jamais chargé.
     * `MessageService::send()` n'autorise rien de son côté — c'est précisément ce qui laissait
     * l'écriture ouverte côté web avant le 2026-08-06.
     */
    private function canalSousGarde(int $channelId, string $capacite): Channel
    {
        $org = $this->organisationActive();

        $canal = Channel::query()
            ->where('organization_account_id', $org->id)
            ->findOrFail($channelId);

        abort_unless(Auth::user()->can($capacite, $canal), 403);

        return $canal;
    }

    // ──────────────────────────────────────────────────────
    // Garde commune
    // ──────────────────────────────────────────────────────

    /**
     * L'organisation active de l'appelant.
     *
     * Un compte sans organisation n'a rien à faire sur cette API : 403 explicite plutôt qu'une
     * requête vide qui laisserait croire à une société sans membres.
     */
    private function exige(string $permission, OrganizationAccount $organisation): void
    {
        abort_unless(
            app(PermissionService::class)->can(Auth::user(), $permission, $organisation),
            403
        );
    }
}
