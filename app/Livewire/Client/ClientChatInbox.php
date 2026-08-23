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
use Livewire\Attributes\Locked;
use Livewire\Component;

class ClientChatInbox extends Component
{
    #[Locked]
    public ?int $activeThreadId = null;

    public string $body = '';

    /**
     * L'ÉCOUTE EST ASSEMBLÉE, PAS DÉCLARÉE — parce qu'il n'y a pas toujours un fil ouvert.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $listeners = ['chat:refresh' => 'refresh'];

        if ($this->activeThreadId) {
            $listeners["echo-private:chat.thread.{$this->activeThreadId},.chat.message"] = 'refresh';
        }

        return $listeners;
    }

    /** Participe-t-il encore à ce fil ? */
    private function participeAuFil(?int $threadId): bool
    {
        if (! $threadId) {
            return false;
        }

        return ChatParticipant::query()
            ->where('thread_id', $threadId)
            ->where('user_id', Auth::id())
            ->whereNull('left_at')
            ->exists();
    }

    public function selectThread(int $threadId): void
    {
        if (! $this->participeAuFil($threadId)) {
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

    /** LE FIL SE MET À JOUR TOUT SEUL — ce n'était pas le cas, et le commentaire précédent (« déclenché par event broadcast côté JS futur ») décrivait un futur qui n'est jamais venu. */
    public function refresh(): void
    {
        unset($this->activeMessages, $this->threads);
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
        if (! $this->participeAuFil($this->activeThreadId)) {
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
        return $this->participeAuFil($this->activeThreadId)
            ? ChatThread::find($this->activeThreadId)
            : null;
    }

    public function render(): View
    {
        return view('livewire.client.client-chat-inbox');
    }
}
