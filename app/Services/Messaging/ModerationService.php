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
                'target_user_id'=> $message->user_id,
                'reason'        => $reason,
            ]);

            broadcast(new MessageDeleted($message))->toOthers();
        });
    }

    public function pinMessage(User $actor, Message $message): void
    {
        if (! $this->policy->pinMessage($actor, $message)) {
            throw new \DomainException("Vous n'êtes pas autorisé à épingler.");
        }

        DB::transaction(function () use ($actor, $message) {
            $message->update([
                'is_pinned' => true,
                'pinned_at' => now(),
                'pinned_by' => $actor->id,
            ]);
            $this->log($actor, $message->channel, ModerationAction::TYPE_PIN_MESSAGE, [
                'message_id' => $message->id,
            ]);
        });
    }

    public function unpinMessage(User $actor, Message $message): void
    {
        if (! $this->policy->pinMessage($actor, $message)) {
            throw new \DomainException("Vous n'êtes pas autorisé à désépingler.");
        }

        DB::transaction(function () use ($actor, $message) {
            $message->update([
                'is_pinned' => false,
                'pinned_at' => null,
                'pinned_by' => null,
            ]);
            $this->log($actor, $message->channel, ModerationAction::TYPE_UNPIN_MESSAGE, [
                'message_id' => $message->id,
            ]);
        });
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

    public function archiveChannel(User $actor, Channel $channel, bool $archive = true): void
    {
        if (! $this->policy->archiveChannel($actor, $channel)) {
            throw new \DomainException("Vous n'êtes pas autorisé à archiver ce canal.");
        }

        DB::transaction(function () use ($actor, $channel, $archive) {
            $channel->update([
                'is_archived' => $archive,
                'archived_at' => $archive ? now() : null,
                'archived_by' => $archive ? $actor->id : null,
            ]);
            $this->log(
                $actor,
                $channel,
                $archive ? ModerationAction::TYPE_ARCHIVE_CHANNEL : ModerationAction::TYPE_UNARCHIVE_CHANNEL
            );
        });
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
}
