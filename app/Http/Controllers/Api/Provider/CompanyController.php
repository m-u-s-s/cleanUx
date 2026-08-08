<?php

namespace App\Http\Controllers\Api\Provider;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\FieldTeam;
use App\Models\Message;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\OrganizationRolePermission;
use App\Models\OrganizationSite;
use App\Models\ProviderSiteAssignment;
use App\Models\Task;
use App\Services\Messaging\MessageService;
use App\Services\Missions\MissionAssignmentService;
use App\Services\Organizations\OrganizationMemberAdministration;
use App\Services\Organizations\ResultatAdministration;
use App\Services\PermissionService;
use App\Services\Tasks\TaskVisibilityService;
use App\Support\Organizations\ResolvesActiveOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function assignMission(Request $request, int $missionId): JsonResponse
    {
        $org = $this->organisationActive();
        $this->exige('missions.dispatch', $org);

        $donnees = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $mission = Mission::query()
            ->where('provider_organization_id', $org->id)
            ->findOrFail($missionId);

        // L'identifiant du travailleur vient du client : il doit désigner un membre ACTIF de cette
        // société, faute de quoi on assignerait une mission à l'employé d'une autre entreprise.
        $travailleur = OrganizationMember::query()
            ->where('organization_account_id', $org->id)
            ->where('user_id', $donnees['user_id'])
            ->where('status', 'active')
            ->firstOrFail();

        // Règle partagée avec l'écran web : libère les autres leads actifs puis synchronise
        // `lead_provider_user_id`. Voir `MissionAssignmentService`.
        app(MissionAssignmentService::class)->assigner($mission, $travailleur);

        return response()->json(['data' => [
            'id' => $mission->id,
            'lead_user_id' => $travailleur->user_id,
        ]]);
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

    public function channelMessages(int $channelId): JsonResponse
    {
        $canal = $this->canalSousGarde($channelId, 'view');

        $messages = Message::query()
            ->where('channel_id', $canal->id)
            ->topLevel()
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
            ]);

        return response()->json(['data' => $messages]);
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
