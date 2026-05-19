<?php

namespace App\Livewire\Admin\ChatV2;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Services\ChatV2\ChatService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ChatCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'threads';   // threads | flagged | blocked
    public string $filterStatus = '';

    public function moderateDelete(int $messageId): void
    {
        $msg = ChatMessage::findOrFail($messageId);
        app(ChatService::class)->moderateMessage($msg, 'delete', Auth::id());
        $this->dispatch('toast', 'Message supprimé.', 'success');
    }

    public function moderateBlock(int $messageId, string $reason = 'Bloqué via admin UI'): void
    {
        $msg = ChatMessage::findOrFail($messageId);
        app(ChatService::class)->moderateMessage($msg, 'block', Auth::id(), $reason);
        $this->dispatch('toast', 'Message bloqué.', 'success');
    }

    public function moderateApprove(int $messageId): void
    {
        $msg = ChatMessage::findOrFail($messageId);
        app(ChatService::class)->moderateMessage($msg, 'approve', Auth::id());
        $this->dispatch('toast', 'Message approuvé.', 'success');
    }

    public function archiveThread(int $threadId): void
    {
        $thread = ChatThread::findOrFail($threadId);
        app(ChatService::class)->archiveThread($thread);
        $this->dispatch('toast', 'Thread archivé.', 'success');
    }

    public function render(): View
    {
        $kpis = [
            'threads_active' => ChatThread::query()->where('status', ChatThread::STATUS_ACTIVE)->count(),
            'threads_archived' => ChatThread::query()->where('is_archived', true)->count(),
            'flagged_messages' => ChatMessage::query()->flagged()->count(),
            'blocked_messages' => ChatMessage::query()->blocked()->count(),
        ];

        if ($this->tab === 'threads') {
            $items = ChatThread::query()
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->orderByDesc('last_message_at')
                ->paginate(25);
        } elseif ($this->tab === 'flagged') {
            $items = ChatMessage::query()
                ->flagged()
                ->with(['thread:id,code,title', 'sender:id,email,name'])
                ->orderByDesc('created_at')
                ->paginate(25);
        } else {
            $items = ChatMessage::query()
                ->blocked()
                ->with(['thread:id,code,title', 'sender:id,email,name'])
                ->orderByDesc('created_at')
                ->paginate(25);
        }

        return view('livewire.admin.chat-v2.chat-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
