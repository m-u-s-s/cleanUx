<?php

namespace App\Livewire\Client;

use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Services\ChatV2\ChatService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ClientChatInbox extends Component
{
    public ?int $activeThreadId = null;
    public string $body = '';

    protected $listeners = ['chat:refresh' => 'refresh'];

    public function selectThread(int $threadId): void
    {
        $belongsToMe = ChatParticipant::query()
            ->where('thread_id', $threadId)
            ->where('user_id', Auth::id())
            ->whereNull('left_at')
            ->exists();
        if (! $belongsToMe) {
            $this->dispatch('toast', 'Vous n\'avez pas accès à ce thread.', 'error');
            return;
        }
        $this->activeThreadId = $threadId;
        // Mark as read
        $thread = ChatThread::find($threadId);
        if ($thread) {
            app(ChatService::class)->markAsRead($thread, Auth::user());
        }
    }

    public function send(): void
    {
        if (! $this->activeThreadId) {
            return;
        }
        $body = trim($this->body);
        if ($body === '') {
            return;
        }
        $thread = ChatThread::find($this->activeThreadId);
        if (! $thread) {
            return;
        }
        try {
            $msg = app(ChatService::class)->sendMessage($thread, Auth::user(), $body);
            $this->body = '';
            if ($msg->moderation_status === ChatMessage::MODERATION_BLOCKED) {
                $this->dispatch('toast', 'Message bloqué par modération (contenu inapproprié).', 'error');
            } elseif ($msg->moderation_status === ChatMessage::MODERATION_FLAGGED) {
                $this->dispatch('toast', 'Message envoyé. Certaines informations sensibles ont été automatiquement masquées.', 'success');
            } else {
                $this->dispatch('toast', 'Message envoyé.', 'success');
            }
        } catch (ValidationException $e) {
            $this->dispatch('toast', implode(' / ', collect($e->errors())->flatten()->all()), 'error');
        }
    }

    public function refresh(): void
    {
        // No-op pour le moment, déclenché par event broadcast côté JS futur.
    }

    #[Computed]
    public function threads()
    {
        return ChatThread::query()
            ->forUser(Auth::id())
            ->where('is_archived', false)
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function activeMessages()
    {
        if (! $this->activeThreadId) {
            return collect();
        }
        return ChatMessage::query()
            ->where('thread_id', $this->activeThreadId)
            ->notDeleted()
            ->orderBy('id')
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function activeThread(): ?ChatThread
    {
        return $this->activeThreadId ? ChatThread::find($this->activeThreadId) : null;
    }

    public function render(): View
    {
        return view('livewire.client.client-chat-inbox');
    }
}
