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

    public function postMessage(\App\Models\User $user, $channel): bool
    {
        if ((bool) ($channel->is_archived ?? false)) {
            return false;
        }

        if ((bool) ($channel->is_locked ?? false)) {
            return $this->isOwnerOrModerator($user, $channel);
        }

        if (($channel->owner_user_id ?? null) === $user->id) {
            return true;
        }

        return $this->isMember($user, $channel);
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

    protected function isMember(\App\Models\User $user, $channel): bool
    {
        foreach (['channel_members', 'message_channel_members', 'channel_user'] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $query = \Illuminate\Support\Facades\DB::table($table);

            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'channel_id')) {
                $query->where('channel_id', $channel->id);
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn($table, 'message_channel_id')) {
                $query->where('message_channel_id', $channel->id);
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'user_id')) {
                $query->where('user_id', $user->id);
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
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

    protected function isOwnerOrModerator(\App\Models\User $user, $channel): bool
    {
        if (($channel->owner_user_id ?? null) === $user->id) {
            return true;
        }

        foreach (['channel_members', 'message_channel_members', 'channel_user'] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $query = \Illuminate\Support\Facades\DB::table($table);

            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'channel_id')) {
                $query->where('channel_id', $channel->id);
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn($table, 'message_channel_id')) {
                $query->where('message_channel_id', $channel->id);
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'user_id')) {
                $query->where('user_id', $user->id);
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'role')) {
                $query->whereIn('role', ['owner', 'moderator', 'admin']);
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }
}
