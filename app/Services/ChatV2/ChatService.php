<?php

namespace App\Services\ChatV2;

use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ChatService
{
    public function __construct(protected ModerationService $moderation) {}

    /**
     * Démarre (ou récupère) un thread pour un contexte donné.
     *
     * @param array<int, array{user_id:int, role:string}> $participants
     */
    public function startThread(
        ?string $contextType,
        ?int $contextId,
        array $participants,
        ?string $title = null,
        array $metadata = [],
    ): ChatThread {
        if ($contextType && ! in_array($contextType, (array) config('chat_v2.allowed_context_types', []), true)) {
            throw ValidationException::withMessages(['context_type' => ['Type de contexte invalide.']]);
        }
        if (empty($participants)) {
            throw ValidationException::withMessages(['participants' => ['Au moins un participant requis.']]);
        }

        // Idempotency : si on a déjà un thread actif pour ce context, on le réutilise
        if ($contextType && $contextId) {
            $existing = ChatThread::query()
                ->forContext($contextType, $contextId)
                ->where('status', ChatThread::STATUS_ACTIVE)
                ->first();
            if ($existing) {
                $this->syncParticipants($existing, $participants);
                return $existing;
            }
        }

        return DB::transaction(function () use ($contextType, $contextId, $participants, $title, $metadata) {
            $thread = ChatThread::query()->create([
                'code' => ChatThread::generateCode(),
                'context_type' => $contextType,
                'context_id' => $contextId,
                'title' => $title,
                'status' => ChatThread::STATUS_ACTIVE,
                'is_archived' => false,
                'message_count' => 0,
                'flagged_count' => 0,
                'metadata' => $metadata,
            ]);
            $this->syncParticipants($thread, $participants);
            return $thread;
        });
    }

    /**
     * @param array<int, array{user_id:int, role:string}> $participants
     */
    protected function syncParticipants(ChatThread $thread, array $participants): void
    {
        foreach ($participants as $p) {
            $userId = (int) ($p['user_id'] ?? 0);
            $role = (string) ($p['role'] ?? ChatParticipant::ROLE_CLIENT);
            if ($userId <= 0) {
                continue;
            }
            ChatParticipant::query()->updateOrCreate(
                ['thread_id' => $thread->id, 'user_id' => $userId],
                [
                    'role' => $role,
                    'is_muted' => false,
                    'can_send' => $role !== ChatParticipant::ROLE_OBSERVER && $role !== ChatParticipant::ROLE_SYSTEM,
                    'joined_at' => now(),
                    'left_at' => null,
                ],
            );
        }
    }

    public function sendMessage(
        ChatThread $thread,
        User $sender,
        string $body,
        ?array $attachment = null,
        ?string $senderRoleOverride = null,
    ): ChatMessage {
        if (! $thread->isOpen()) {
            throw ValidationException::withMessages(['thread' => ['Thread fermé ou archivé.']]);
        }

        $participant = ChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $sender->id)
            ->whereNull('left_at')
            ->first();
        if (! $participant) {
            throw ValidationException::withMessages(['thread' => ['Utilisateur non participant.']]);
        }
        if (! $participant->canSendMessages()) {
            throw ValidationException::withMessages(['thread' => ['Participant muet ou en lecture seule.']]);
        }

        $body = trim($body);
        $min = (int) config('chat_v2.min_message_length', 1);
        $max = (int) config('chat_v2.max_message_length', 4096);
        if (mb_strlen($body) < $min && empty($attachment)) {
            throw ValidationException::withMessages(['body' => ['Message trop court.']]);
        }
        if (mb_strlen($body) > $max) {
            throw ValidationException::withMessages(['body' => ["Message trop long (> {$max} caractères)."]]);
        }

        $moderation = $body !== ''
            ? $this->moderation->scan($body)
            : new ModerationResult('clean', null, $body, null);

        if ($moderation->isBlocked()) {
            // On persiste quand même la row pour audit, status=blocked, body original conservé mais displayBody renvoie placeholder
            $msg = ChatMessage::query()->create([
                'thread_id' => $thread->id,
                'sender_user_id' => $sender->id,
                'sender_role' => $senderRoleOverride ?: $participant->role,
                'body' => $body,
                'is_redacted' => false,
                'body_original_hash' => $moderation->originalHash,
                'moderation_status' => ChatMessage::MODERATION_BLOCKED,
                'moderation_reason' => $moderation->reason,
            ]);
            $thread->increment('flagged_count');
            return $msg;
        }

        $persistedBody = $moderation->redactedBody;

        return DB::transaction(function () use ($thread, $sender, $persistedBody, $body, $moderation, $attachment, $participant, $senderRoleOverride) {
            $msg = ChatMessage::query()->create([
                'thread_id' => $thread->id,
                'sender_user_id' => $sender->id,
                'sender_role' => $senderRoleOverride ?: $participant->role,
                'body' => $persistedBody,
                'is_redacted' => $moderation->isFlagged(),
                'body_original_hash' => $moderation->isFlagged() ? $moderation->originalHash : null,
                'attachment_path' => $attachment['path'] ?? null,
                'attachment_mime' => $attachment['mime'] ?? null,
                'attachment_size_bytes' => $attachment['size_bytes'] ?? null,
                'moderation_status' => $moderation->isClean() ? ChatMessage::MODERATION_CLEAN : ChatMessage::MODERATION_FLAGGED,
                'moderation_reason' => $moderation->reason,
            ]);

            $thread->update([
                'last_message_at' => now(),
                'last_message_preview' => mb_substr(strip_tags($persistedBody), 0, 180),
                'message_count' => $thread->message_count + 1,
                'flagged_count' => $moderation->isFlagged() ? $thread->flagged_count + 1 : $thread->flagged_count,
            ]);

            // Mark sender as having read his own message
            ChatMessageRead::query()->updateOrCreate(
                ['message_id' => $msg->id, 'user_id' => $sender->id],
                ['read_at' => now()],
            );
            $participant->update([
                'last_read_at' => now(),
                'last_read_message_id' => $msg->id,
            ]);

            // Broadcast best-effort
            $this->broadcastMessage($thread, $msg);

            return $msg;
        });
    }

    public function markAsRead(ChatThread $thread, User $user, ?int $upToMessageId = null): int
    {
        $participant = ChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();
        if (! $participant) {
            return 0;
        }

        $q = ChatMessage::query()->where('thread_id', $thread->id)->notDeleted();
        if ($upToMessageId) {
            $q->where('id', '<=', $upToMessageId);
        }
        if ($participant->last_read_message_id) {
            $q->where('id', '>', $participant->last_read_message_id);
        }
        $msgs = $q->get(['id']);

        $count = 0;
        $lastId = $participant->last_read_message_id;
        foreach ($msgs as $m) {
            ChatMessageRead::query()->updateOrCreate(
                ['message_id' => $m->id, 'user_id' => $user->id],
                ['read_at' => now()],
            );
            $lastId = $m->id;
            $count++;
        }
        $participant->update([
            'last_read_at' => now(),
            'last_read_message_id' => $lastId,
        ]);
        return $count;
    }

    public function archiveThread(ChatThread $thread): ChatThread
    {
        $thread->update([
            'status' => ChatThread::STATUS_ARCHIVED,
            'is_archived' => true,
        ]);
        return $thread->fresh();
    }

    public function lockThread(ChatThread $thread): ChatThread
    {
        $thread->update(['status' => ChatThread::STATUS_LOCKED]);
        return $thread->fresh();
    }

    public function moderateMessage(ChatMessage $message, string $action, ?int $adminUserId = null, ?string $reason = null): ChatMessage
    {
        return DB::transaction(function () use ($message, $action, $adminUserId, $reason) {
            switch ($action) {
                case 'delete':
                    $message->update([
                        'is_deleted' => true,
                        'deleted_at' => now(),
                        'deleted_by_user_id' => $adminUserId,
                    ]);
                    break;
                case 'block':
                    $message->update([
                        'moderation_status' => ChatMessage::MODERATION_BLOCKED,
                        'moderation_reason' => $reason ?? 'admin_block',
                    ]);
                    break;
                case 'approve':
                    $message->update([
                        'moderation_status' => ChatMessage::MODERATION_CLEAN,
                        'moderation_reason' => null,
                    ]);
                    if ($message->thread && $message->thread->flagged_count > 0) {
                        $message->thread->decrement('flagged_count');
                    }
                    break;
                default:
                    throw ValidationException::withMessages(['action' => ['Action de modération invalide.']]);
            }
            return $message->fresh();
        });
    }

    protected function broadcastMessage(ChatThread $thread, ChatMessage $message): void
    {
        if (! (bool) config('chat_v2.broadcast_enabled', true)) {
            return;
        }
        try {
            $eventClass = \App\Events\ChatV2\ChatMessageSentEvent::class;
            if (class_exists($eventClass)) {
                Event::dispatch(new $eventClass($thread, $message));
            }
        } catch (\Throwable $e) {
            Log::warning('[chat_v2] broadcast failed', ['error' => $e->getMessage()]);
        }
    }
}
