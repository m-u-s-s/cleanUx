<?php

namespace App\Services\Messaging;

use App\Events\Messaging\MessageDeleted;
use App\Models\Channel;
use App\Models\Message;
use App\Models\ModerationAction;
use App\Models\User;
use App\Policies\ChannelPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Service centralisé pour les actions de modération.
 *
 * Toutes les méthodes :
 *   1) vérifient le droit via ChannelPolicy (sinon DomainException)
 *   2) effectuent l'action en transaction
 *   3) journalisent dans moderation_actions
 *   4) broadcast l'event approprié pour rafraîchir l'UI live
 */
class ModerationService
{
    public function __construct(
        protected ChannelPolicy $policy,
    ) {}

    public function deleteMessageAsModerator(User $actor, Message $message, ?string $reason = null): void
    {
        if (! $this->policy->deleteMessage($actor, $message)) {
            throw new \DomainException("Vous n'êtes pas autorisé à supprimer ce message.");
        }

        DB::transaction(function () use ($actor, $message, $reason) {
            $message->update([
                'deleted_by'     => $actor->id,
                'deleted_reason' => $reason,
            ]);
            $message->delete(); // soft delete

            if ($message->parent_id) {
                Message::find($message->parent_id)?->refreshThreadStats();
            }

            $this->log($actor, $message->channel, ModerationAction::TYPE_DELETE_MESSAGE, [
                'message_id'    => $message->id,
                'target_user_id' => $message->user_id,
                'reason'        => $reason,
            ]);

            broadcast(new MessageDeleted($message))->toOthers();
        });
    }

    public function pinMessage(User $actor, Message $message): void
    {
        if (! $this->policy->pinMessage($actor, $message)) {
            throw new \DomainException("Vous n'êtes pas autorisé à épingler ce message.");
        }

        $message->forceFill([
            'is_pinned' => true,
            'pinned_by' => $actor->id,
            'pinned_at' => now(),
        ])->save();

        $this->logAction($actor, $message->channel, $message, 'message_pinned');
    }

    public function unpinMessage(\App\Models\User $actor, $message): void
    {
        $message->forceFill([
            'is_pinned' => false,
            'pinned_by' => null,
            'pinned_at' => null,
        ])->save();

        $this->logAction($actor, $message->channel ?? null, $message, 'message_unpinned', null);
    }


    public function lockChannel(User $actor, Channel $channel, bool $lock = true, ?string $reason = null): void
    {
        if (! $this->policy->lockChannel($actor, $channel)) {
            throw new \DomainException("Vous n'êtes pas autorisé à verrouiller ce canal.");
        }

        DB::transaction(function () use ($actor, $channel, $lock, $reason) {
            $channel->update(['is_locked' => $lock]);
            $this->log(
                $actor,
                $channel,
                $lock ? ModerationAction::TYPE_LOCK_CHANNEL : ModerationAction::TYPE_UNLOCK_CHANNEL,
                ['reason' => $reason]
            );
        });
    }

    public function archiveChannel(User $actor, Channel $channel): void
    {
        if (! $this->policy->archiveChannel($actor, $channel)) {
            throw new \DomainException("Vous n'êtes pas autorisé à archiver ce canal.");
        }

        $channel->forceFill([
            'is_archived' => true,
            'archived_at' => now(),
            'archived_by' => $actor->id,
        ])->save();

        $this->logAction($actor, $channel, null, 'channel_archived');
    }


    public function kickMember(User $actor, Channel $channel, User $target, ?string $reason = null): void
    {
        if (! $this->policy->kickMember($actor, $channel, $target)) {
            throw new \DomainException("Vous n'êtes pas autorisé à exclure ce membre.");
        }

        DB::transaction(function () use ($actor, $channel, $target, $reason) {
            $channel->members()->detach($target->id);

            $this->log($actor, $channel, ModerationAction::TYPE_KICK_MEMBER, [
                'target_user_id' => $target->id,
                'reason'         => $reason,
            ]);
        });
    }

    public function changeMemberRole(User $actor, Channel $channel, User $target, string $newRole): void
    {
        if (! $this->policy->changeRole($actor, $channel, $target)) {
            throw new \DomainException("Vous n'êtes pas autorisé à changer ce rôle.");
        }

        $allowed = [
            ChannelPolicy::ROLE_MODERATOR,
            ChannelPolicy::ROLE_MEMBER,
            ChannelPolicy::ROLE_READONLY,
        ];
        if (! in_array($newRole, $allowed, true)) {
            throw new \DomainException("Rôle invalide : {$newRole}");
        }

        DB::transaction(function () use ($actor, $channel, $target, $newRole) {
            $previous = $channel->members()->where('user_id', $target->id)->first()?->pivot->role;

            $channel->members()->updateExistingPivot($target->id, [
                'role' => $newRole,
            ]);

            $this->log($actor, $channel, ModerationAction::TYPE_ROLE_CHANGE, [
                'target_user_id' => $target->id,
                'from'           => $previous,
                'to'             => $newRole,
            ]);
        });
    }

    private function log(User $actor, Channel $channel, string $type, array $payload = []): ModerationAction
    {
        return ModerationAction::create([
            'actor_user_id'  => $actor->id,
            'channel_id'     => $channel->id,
            'message_id'     => $payload['message_id']     ?? null,
            'target_user_id' => $payload['target_user_id'] ?? null,
            'action_type'    => $type,
            'reason'         => $payload['reason']         ?? null,
            'payload'        => $payload,
        ]);
    }

    protected function logAction(\App\Models\User $actor, $channel = null, $message = null, string $action = 'moderation_action', ?string $reason = null): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('moderation_actions')) {
            return;
        }

        $table = 'moderation_actions';
        $data = [];

        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'actor_user_id')) {
            $data['actor_user_id'] = $actor->id;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'user_id')) {
            $data['user_id'] = $actor->id;
        }

        if ($channel && \Illuminate\Support\Facades\Schema::hasColumn($table, 'channel_id')) {
            $data['channel_id'] = $channel->id;
        }

        if ($message && \Illuminate\Support\Facades\Schema::hasColumn($table, 'message_id')) {
            $data['message_id'] = $message->id;
        }

        foreach (['action_type', 'type', 'action'] as $col) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, $col)) {
                $data[$col] = $action;
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'reason')) {
            $data['reason'] = $reason;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'metadata')) {
            $data['metadata'] = json_encode(['reason' => $reason]);
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'created_at')) {
            $data['created_at'] = now();
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'updated_at')) {
            $data['updated_at'] = now();
        }

        if (! empty($data)) {
            \Illuminate\Support\Facades\DB::table($table)->insert($data);
        }
    }
}
