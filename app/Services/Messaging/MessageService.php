<?php

namespace App\Services\Messaging;

use App\Events\MessageSent;
use App\Events\Messaging\MessageDeleted;
use App\Events\Messaging\MessageEdited;
use App\Events\Messaging\UserMentioned;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Notifications\MentionedInMessageNotification;
use App\Services\ChatV2\ModerationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Orchestrateur central pour les messages. */
class MessageService
{
    public function __construct(
        protected MentionParser $mentionParser,
    ) {}

    /** LA MODÉRATION DE CHATV2, APPLIQUÉE AUX CANAUX INTERNES. */
    protected function passerLaModeration(string $content, string $type): string
    {
        if ($type === Message::TYPE_SYSTEM || ! config('messaging.moderation.channels', false)) {
            return $content;
        }

        $verdict = app(ModerationService::class)->scan($content);

        // Bloqué = REFUSÉ, pas caviardé.
        if ($verdict->isBlocked()) {
            throw new \DomainException(
                $verdict->reason ?? 'Ce message ne peut pas être envoyé.'
            );
        }

        return $verdict->redactedBody;
    }

    /** Crée un message dans un channel. */
    public function send(
        Channel $channel,
        User $sender,
        string $content,
        ?int $parentId = null,
        string $type = Message::TYPE_TEXT,
        array $metadata = [],
    ): Message {
        $content = $this->passerLaModeration($content, $type);

        return DB::transaction(function () use ($channel, $sender, $content, $parentId, $type, $metadata) {

            $message = Message::create([
                'channel_id' => $channel->id,
                'user_id' => $sender->id,
                'content' => $content,
                'type' => $type,
                'parent_id' => $parentId,
                'metadata' => $metadata ?: null,
            ]);

            // Mentions
            $resolved = $this->mentionParser->extractAndPersist($message);

            // Thread stats
            if ($parentId) {
                $parent = Message::find($parentId);
                $parent?->refreshThreadStats();
            }

            // Notifs ciblées
            $this->notifyMentioned($message, $resolved['users']);

            // Broadcast (le listener Livewire #[On('echo-private:channel.{id},MessageSent')] se déclenche)
            broadcast(new MessageSent($message))->toOthers();

            // Si mention spéciale @channel, on dispatche un event pour mettre tous les membres en notif badge
            foreach ($resolved['users'] as $u) {
                broadcast(new UserMentioned($message, $u))->toOthers();
            }

            return $message->fresh(['sender', 'mentions', 'attachments']);
        });
    }

    public function edit(Message $message, User $editor, string $newContent): Message
    {
        if ((int) $message->user_id !== (int) $editor->id) {
            throw new \DomainException('Vous ne pouvez éditer que vos propres messages.');
        }

        return DB::transaction(function () use ($message, $newContent) {
            $message->content = $newContent;
            $message->edited_at = now();
            $message->save();

            // Re-parser les mentions (delete + recreate)
            $message->mentions()->delete();
            $resolved = $this->mentionParser->extractAndPersist($message);
            $this->notifyMentioned($message, $resolved['users'], onlyNew: true);

            broadcast(new MessageEdited($message))->toOthers();

            return $message->fresh();
        });
    }

    public function delete(Message $message, User $actor): void
    {
        $isAuthor = (int) $message->user_id === (int) $actor->id;

        // Modérateurs / Admin peuvent supprimer aussi (vérification déléguée à PolicyService côté Controller)
        if (! $isAuthor && ! $actor->isAdmin()) {
            throw new \DomainException('Vous ne pouvez supprimer que vos propres messages.');
        }

        DB::transaction(function () use ($message) {
            $message->delete(); // soft delete

            if ($message->parent_id) {
                Message::find($message->parent_id)?->refreshThreadStats();
            }

            broadcast(new MessageDeleted($message))->toOthers();
        });
    }

    /**
     * @param  array<int, User>  $users
     */
    protected function notifyMentioned(Message $message, array $users, bool $onlyNew = false): void
    {
        if (empty($users)) {
            return;
        }

        $recipients = collect($users)
            ->reject(fn (User $u) => (int) $u->id === (int) $message->user_id) // pas se notifier soi-même
            ->values()
            ->all();

        if (empty($recipients)) {
            return;
        }

        Notification::send($recipients, new MentionedInMessageNotification($message));
    }
}
