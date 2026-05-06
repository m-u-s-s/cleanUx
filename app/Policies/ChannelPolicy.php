<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;

/**
 * Phase 4.1 — Policy de modération sur Channel et Message.
 *
 * Hiérarchie des rôles dans channel_members.role :
 *   - owner     : créateur, droits complets
 *   - moderator : peut delete/pin/lock + kick membres
 *   - member    : peut poster, supprimer ses propres messages
 *   - readonly  : ne peut que lire
 *
 * + super-cas : Platform Admin (User::isPlatformAdmin) outrepasse tout.
 */
class ChannelPolicy
{
    public const ROLE_OWNER     = 'owner';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_MEMBER    = 'member';
    public const ROLE_READONLY  = 'readonly';

    /** Voir le canal (être membre suffit). */
    public function view(User $user, Channel $channel): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }
        return $this->isMember($user, $channel);
    }

    /** Poster un message dans le canal. */
    public function postMessage(User $user, Channel $channel): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        // Canal archivé/locké : seuls modérateurs+ peuvent poster
        if ($channel->is_archived) {
            return false;
        }

        if ($channel->is_locked) {
            return $this->isAtLeast($user, $channel, self::ROLE_MODERATOR);
        }

        $role = $this->roleIn($user, $channel);
        return $role !== null && $role !== self::ROLE_READONLY;
    }

    /** Supprimer un message : auteur OU modérateur+. */
    public function deleteMessage(User $user, Message $message): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        // Auteur peut supprimer le sien (sauf si verrouillé en lock par modération)
        if ((int) $message->user_id === (int) $user->id) {
            return true;
        }

        // Sinon il faut être moderator+ du canal
        return $this->isAtLeast($user, $message->channel, self::ROLE_MODERATOR);
    }

    /** Épingler/désépingler un message : modérateur+. */
    public function pinMessage(User $user, Message $message): bool
    {
        if ($this->isPlatformAdmin($user)) return true;
        return $this->isAtLeast($user, $message->channel, self::ROLE_MODERATOR);
    }

    /** Lock / unlock du canal : modérateur+. */
    public function lockChannel(User $user, Channel $channel): bool
    {
        if ($this->isPlatformAdmin($user)) return true;
        return $this->isAtLeast($user, $channel, self::ROLE_MODERATOR);
    }

    /** Archive / unarchive : owner uniquement. */
    public function archiveChannel(User $user, Channel $channel): bool
    {
        if ($this->isPlatformAdmin($user)) return true;
        return $this->isAtLeast($user, $channel, self::ROLE_OWNER);
    }

    /** Kicker un membre : modérateur+ (sauf l'owner). */
    public function kickMember(User $user, Channel $channel, User $target): bool
    {
        if ($this->isPlatformAdmin($user)) return true;

        // On ne peut pas kicker l'owner
        $targetRole = $this->roleIn($target, $channel);
        if ($targetRole === self::ROLE_OWNER) {
            return false;
        }

        return $this->isAtLeast($user, $channel, self::ROLE_MODERATOR);
    }

    /** Promouvoir/démotion : owner uniquement. */
    public function changeRole(User $user, Channel $channel, User $target): bool
    {
        if ($this->isPlatformAdmin($user)) return true;
        if ((int) $user->id === (int) $target->id) return false; // pas se promouvoir
        return $this->isAtLeast($user, $channel, self::ROLE_OWNER);
    }

    // ──────────────────────────────────────────────────────
    // Helpers internes
    // ──────────────────────────────────────────────────────

    private function isPlatformAdmin(User $user): bool
    {
        return method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin();
    }

    private function isMember(User $user, Channel $channel): bool
    {
        return $channel->members()->where('user_id', $user->id)->exists();
    }

    private function roleIn(User $user, Channel $channel): ?string
    {
        $member = $channel->members()->where('user_id', $user->id)->first();
        return $member?->pivot->role;
    }

    private function isAtLeast(User $user, Channel $channel, string $minRole): bool
    {
        $rank = [
            self::ROLE_READONLY  => 0,
            self::ROLE_MEMBER    => 1,
            self::ROLE_MODERATOR => 2,
            self::ROLE_OWNER     => 3,
        ];

        $userRole = $this->roleIn($user, $channel);
        if (! $userRole) {
            return false;
        }

        return ($rank[$userRole] ?? -1) >= ($rank[$minRole] ?? 99);
    }
}
