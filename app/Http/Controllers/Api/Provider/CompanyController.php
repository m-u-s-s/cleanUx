<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\FieldTeam;
use App\Models\Message;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Services\Messaging\MessageService;
use App\Services\Missions\MissionAssignmentService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

    public function tasks(): JsonResponse
    {
        $org = $this->organisationActive();

        $taches = Task::query()
            ->where('organization_account_id', $org->id)
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
