<?php

use App\Models\Channel;
use App\Models\ChatThread;
use App\Models\FieldTeam;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * Phase 3 — Authorization channels.
 *
 * Chaque PrivateChannel ou PresenceChannel doit avoir une callback ici.
 * Si une callback retourne `false` ou `null`, l'utilisateur est rejeté.
 * Pour les presence channels, retourner un array de meta sur l'utilisateur.
 */

// ──────────────────────────────────────────────────────
// Mission tracking GPS (existant — conservé)
// ──────────────────────────────────────────────────────
Broadcast::channel('mission.{missionId}', function (User $user, int $missionId) {
    $mission = Mission::query()
        ->with(['rendezVous', 'assignments'])
        ->find($missionId);

    if (! $mission) {
        return false;
    }

    if ($user->isAdmin()) {
        return true;
    }

    // Phase 11+ — accepter aussi `lead_provider_user_id` (Phase 11) en plus
    // de `lead_employee_id` (historique). Sans cette double-vérif, un
    // prestataire qui a accepté via /api/provider/assignments/{id}/accept
    // se voit refuser l'abonnement au channel mission.
    if ((int) ($mission->lead_provider_user_id ?? 0) === (int) $user->id) {
        return true;
    }

    if ($user->isEmploye()) {
        return (int) $mission->lead_employee_id === (int) $user->id
            || $mission->assignments()->where('user_id', $user->id)->exists();
    }

    if ($user->isClient()) {
        return (int) $mission->rendezVous?->client_id === (int) $user->id
            || (
                $mission->organization_account_id
                && $user->organization_account_id
                && (int) $mission->organization_account_id === (int) $user->organization_account_id
            );
    }

    return false;
});

// ──────────────────────────────────────────────────────
// PHASE 3 BUGFIX — Chat équipe (channels)
// ──────────────────────────────────────────────────────
// L'event App\Events\MessageSent diffuse sur PrivateChannel('channel.' . $channelId).
// Sans cette autorisation, le frontend Echo recevait un 403 silencieux et le
// listener Livewire ne se déclenchait jamais.
Broadcast::channel('channel.{channelId}', function (User $user, int $channelId) {
    $channel = Channel::with('organization')->find($channelId);

    if (! $channel) {
        return false;
    }

    if ($user->isAdmin()) {
        return true;
    }

    // L'utilisateur doit être membre du canal
    $isMember = $channel->members()
        ->where('user_id', $user->id)
        ->exists();

    if (! $isMember) {
        return false;
    }

    // Cohérence d'organisation (sécurité supplémentaire)
    if ($channel->organization_account_id
        && $user->organization_account_id
        && (int) $channel->organization_account_id !== (int) $user->organization_account_id) {
        return false;
    }

    return true;
});

// ──────────────────────────────────────────────────────
// PHASE 3 — Presence Channel : organisation
// ──────────────────────────────────────────────────────
// Renvoie un payload qui s'affichera dans la liste des "users online" du frontend.
// Echo.join('presence-org.123') lance le call et reçoit en retour les autres users.
Broadcast::channel('presence-org.{orgId}', function (User $user, int $orgId) {
    if ($user->isAdmin()) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => 'admin',
            'avatar_url' => method_exists($user, 'getAvatarUrl') ? $user->getAvatarUrl() : null,
        ];
    }

    // L'utilisateur doit appartenir à cette organisation
    $org = OrganizationAccount::find($orgId);
    if (! $org) {
        return null;
    }

    /*
     * L'APPARTENANCE SE LIT SUR L'ADHESION, PAS SUR `users.organization_account_id`.
     *
     * Cette colonne porte l'organisation CLIENTE d'un compte. La societe d'un SALARIE vit sur
     * `provider_profiles.organization_account_id` : un worker n'entrait donc jamais dans le canal
     * de presence de sa propre societe, et le dispatch ne le voyait pas en ligne.
     *
     * L'adhesion active fait autorite — c'est elle qui porte le role —, le profil prestataire sert
     * de repli pour les comptes rattaches sans ligne d'adhesion.
     */
    $adhesion = OrganizationMember::query()
        ->where('organization_account_id', $orgId)
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->first();

    $rattacheParSonProfil = (int) ($user->providerProfile?->organization_account_id ?? 0) === (int) $orgId;

    if (! $adhesion && ! $rattacheParSonProfil && (int) $user->organization_account_id !== (int) $orgId) {
        return null;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $adhesion?->role?->value ?? $user->organization_role ?? 'member',
        'avatar_url' => method_exists($user, 'getAvatarUrl') ? $user->getAvatarUrl() : null,
    ];
});

// ──────────────────────────────────────────────────────
// PHASE 3 — Presence Channel : équipe terrain
// ──────────────────────────────────────────────────────
Broadcast::channel('presence-team.{teamId}', function (User $user, int $teamId) {
    $team = FieldTeam::with('members')->find($teamId);
    if (! $team) {
        return null;
    }

    if ($user->isAdmin()) {
        return ['id' => $user->id, 'name' => $user->name, 'role' => 'admin'];
    }

    $isMember = $team->members()->where('user_id', $user->id)->exists();
    if (! $isMember) {
        return null;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => 'member',
    ];
});

// ──────────────────────────────────────────────────────
// PHASE 3 — User-only private channel pour notifications personnelles
// ──────────────────────────────────────────────────────
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return (int) $user->id === (int) $userId;
});

// ──────────────────────────────────────────────────────
// Présence des prestataires — canal PLATEFORME, pas d'organisation
// ──────────────────────────────────────────────────────
/*
 * CE CANAL DIFFUSE LA POSITION SOCIALE DE TOUS LES PRESTATAIRES DE LA PLATEFORME.
 * `ProviderPresenceChanged` y envoie « untel vient de passer en ligne / hors ligne », pour tout le
 * monde, sans filtre d'organisation. C'est un canal d'orchestration terrain, réservé à ceux qui
 * pilotent le dispatch.
 *
 * LA GARDE SE LISAIT SUR `users.role`, COLONNE DÉPRÉCIÉE — ET C'ÉTAIT LA MAUVAISE COLONNE.
 * Deux problèmes se combinaient :
 *
 *   1. `role` est déprécié depuis la migration `drop_legacy_role_from_users` : la source de vérité
 *      du rang plateforme est `platform_role`, lue par `isPlatformAdmin()`. Un vrai administrateur
 *      créé sans `role` passait donc par la troisième branche, et un compte portant `role=admin`
 *      sans `platform_role` entrait alors qu'il n'est administrateur de rien.
 *
 *   2. `role === 'dispatcher'` ne correspondait à AUCUN compte légitime : `dispatcher` est un rôle
 *      d'ORGANISATION (`OrganizationRole::DISPATCHER`, table `organization_members`), jamais une
 *      valeur de `users.role`. Cette branche n'ouvrait la porte qu'à ce qui aurait écrit `dispatcher`
 *      dans cette colonne — et l'inscription publique le pouvait, `role` étant assignable en masse
 *      et `CreateNewUser` recevant `$request->all()`. Un simple champ caché à l'inscription
 *      suffisait à écouter la présence de tous les prestataires.
 *
 * LA PERMISSION EXISTE DÉJÀ, ON N'EN INVENTE PAS : `manage-orchestration` (« Orchestration
 * terrain ») est déclarée dans `HasAdminCapabilities::allowedAdminPermissions()` et garde déjà la
 * simulation de matching (`MatchingSimulationController::simulate`) et l'écran d'orchestration
 * terrain. C'est exactement la même fonction métier — savoir qui est disponible pour prendre une
 * mission. On reprend donc ici l'expression que ce contrôleur utilise déjà, pour que le canal
 * temps réel et l'écran qu'il alimente s'ouvrent aux mêmes personnes.
 *
 * Les deux branches passent par `canAccessAdminModule()`, qui vérifie en une fois le rang
 * plateforme (`admin`/`super_admin`) ET le compte actif : la permission le raffine, le rang seul
 * suffit — et un compte désactivé n'écoute plus rien, ce que l'ancienne garde ne regardait pas.
 *
 * DÉLIBÉRÉMENT PAS `hasAdminPermission()` : celle-là ne lit que le tableau `permissions`, sans
 * regarder `platform_role`. Or `permissions` est encore assignable en masse sur `User` ; s'en
 * servir seul rouvrirait par la fenêtre la porte qu'on vient de fermer.
 */
Broadcast::channel('providers.presence', function (User $user) {
    return $user->canAccessAdminModule('manage-orchestration')
        || $user->canAccessAdminModule();
});

// ──────────────────────────────────────────────────────
// ChatV2 — fil de discussion client ↔ prestataire
// ──────────────────────────────────────────────────────
/*
 * SANS CETTE AUTORISATION, LE CHAT CLIENT NE MARCHAIT QUE PAR RECHARGEMENT.
 *
 * `ChatMessageSentEvent` diffuse depuis toujours sur `chat.thread.{id}`, mais aucune callback ne
 * couvrait ce motif : Echo recevait un 403 à chaque souscription, en silence. Les deux
 * interlocuteurs se parlaient sans jamais se voir écrire — il fallait rafraîchir la page pour
 * découvrir la réponse. Sur une intervention en cours, cela veut dire un client qui pose une
 * question depuis son salon et un prestataire devant la porte qui ne la lit jamais.
 *
 * LA CONDITION EST LA PARTICIPATION VIVANTE, `left_at` COMPRIS. Un participant retiré du fil garde
 * sa session Echo ouverte : sans ce filtre, il continuerait de recevoir les messages d'une
 * conversation dont on vient de l'écarter. C'est exactement le scénario que R2 rend possible en
 * ouvrant le retrait de participant.
 */
Broadcast::channel('chat.thread.{threadId}', function (User $user, int $threadId) {
    $thread = ChatThread::query()->find($threadId);

    if (! $thread) {
        return false;
    }

    // L'admin lit tout : la modération des fils passe par les mêmes écrans.
    if ($user->isAdmin()) {
        return true;
    }

    return $thread->participants()
        ->where('user_id', $user->id)
        ->whereNull('left_at')
        ->exists();
});
