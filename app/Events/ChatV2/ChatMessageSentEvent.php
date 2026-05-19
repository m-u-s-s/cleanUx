<?php

namespace App\Events\ChatV2;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Realtime\Contracts\TracksBroadcastLedger;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Chat message broadcast on private channel chat.thread.{id}.
 * Implements TracksBroadcastLedger pour idempotency + audit.
 */
class ChatMessageSentEvent implements ShouldBroadcastNow, TracksBroadcastLedger
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public ChatThread $thread,
        public ChatMessage $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.thread.' . $this->thread->id)];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'thread_id' => $this->thread->id,
            'sender_user_id' => $this->message->sender_user_id,
            'sender_role' => $this->message->sender_role,
            'body' => $this->message->displayBody(),
            'has_attachment' => $this->message->attachment_path !== null,
            'attachment_mime' => $this->message->attachment_mime,
            'moderation_status' => $this->message->moderation_status,
            'created_at' => optional($this->message->created_at)->toIso8601String(),
        ];
    }

    public function broadcastCategory(): string
    {
        return \App\Models\BroadcastEvent::CATEGORY_CHAT;
    }

    public function broadcastIdempotencyKey(): ?string
    {
        return 'chat:message:' . $this->message->id;
    }

    public function broadcastSourceType(): ?string
    {
        return ChatMessage::class;
    }

    public function broadcastSourceId(): ?int
    {
        return $this->message->id;
    }

    public function broadcastLedgerPayload(): array
    {
        return $this->broadcastWith();
    }
}
