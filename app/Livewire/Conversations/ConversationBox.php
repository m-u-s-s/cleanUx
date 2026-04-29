<?php

namespace App\Livewire\Conversations;

use Livewire\Component;

class ConversationBox extends Component
{
    public Conversation $conversation;
    public string $message = '';

    protected function notifyParticipants($message): void
    {
        $conversation = $this->conversation->load([
            'rendezVous.client',
            'rendezVous.employe',
            'mission.leadEmployee',
        ]);

        $participants = collect([
            $conversation->rendezVous?->client,
            $conversation->rendezVous?->employe,
            $conversation->mission?->leadEmployee,
        ])
            ->filter()
            ->unique('id')
            ->reject(fn($user) => $user->id === auth()->id());

        foreach ($participants as $user) {
            $user->notify(new \App\Notifications\NewConversationMessageNotification($message));
        }
    }
    
    public function send(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $this->conversation->messages()->create([
            'sender_id' => auth()->id(),
            'message' => $this->message,
        ]);
        $this->notifyParticipants($message);

        $this->message = '';
        $this->conversation->refresh();
    }

    public function render()
    {
        return view('livewire.conversations.conversation-box', [
            'messages' => $this->conversation
                ->messages()
                ->with('sender')
                ->latest()
                ->take(50)
                ->get()
                ->reverse(),
        ]);
    }
}
