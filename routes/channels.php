<?php

use App\Models\Channel;
use App\Models\Mission;
use App\Models\OrganizationAccount;
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
            'id'         => $user->id,
            'name'       => $user->name,
            'role'       => 'admin',
            'avatar_url' => method_exists($user, 'getAvatarUrl') ? $user->getAvatarUrl() : null,
        ];
    }

    // L'utilisateur doit appartenir à cette organisation
    $org = OrganizationAccount::find($orgId);
    if (! $org) {
        return null;
    }

    if ((int) $user->organization_account_id !== (int) $orgId) {
        return null;
    }

    return [
        'id'         => $user->id,
        'name'       => $user->name,
        'role'       => $user->organization_role ?? 'member',
        'avatar_url' => method_exists($user, 'getAvatarUrl') ? $user->getAvatarUrl() : null,
    ];
});

// ──────────────────────────────────────────────────────
// PHASE 3 — Presence Channel : équipe terrain
// ──────────────────────────────────────────────────────
Broadcast::channel('presence-team.{teamId}', function (User $user, int $teamId) {
    $team = \App\Models\FieldTeam::with('members')->find($teamId);
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
        'id'   => $user->id,
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

Broadcast::channel('providers.presence', function ($user) {
    // Seuls les admins / dispatchers peuvent écouter
    return $user && (
        $user->role === 'admin' ||
        $user->role === 'dispatcher' ||
        method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin()
    );
});
