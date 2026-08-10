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
use Livewire\Component;

class ClientChatInbox extends Component
{
    public ?int $activeThreadId = null;

    public string $body = '';

    /**
     * L'ÉCOUTE EST ASSEMBLÉE, PAS DÉCLARÉE — parce qu'il n'y a pas toujours un fil ouvert.
     *
     * Livewire interpole `{activeThreadId}` dans le nom de l'événement, et LÈVE une exception quand
     * la propriété vaut nul : la boîte de réception plantait à l'affichage tant qu'aucune
     * conversation n'était sélectionnée. Les deux composants voisins qui emploient ce motif
     * (`DispatchCenter`, `TeamChannels`) l'évitent en typant leur identifiant `int = 0` — ils
     * s'abonnent alors à un canal `…0` inexistant, ce qui est inoffensif mais confus. Ici
     * l'identifiant est légitimement nullable, donc on n'ajoute l'abonnement que lorsqu'il existe.
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

    /**
     * LE FIL SE MET À JOUR TOUT SEUL — ce n'était pas le cas, et le commentaire précédent
     * (« déclenché par event broadcast côté JS futur ») décrivait un futur qui n'est jamais venu.
     *
     * Trois pièces manquaient, et il fallait les trois : l'autorisation du canal côté serveur
     * (absente de `routes/channels.php`, donc 403 silencieux), l'abonnement mobile sur le bon canal
     * et le bon événement, et cette écoute-ci côté web. Le client posait une question depuis son
     * salon, le prestataire devant la porte ne la voyait jamais — chacun devait recharger pour
     * découvrir que l'autre avait parlé.
     *
     * Le nom du canal suit le motif déjà employé par la messagerie interne
     * (`echo-private:channel.{id},MessageSent`) : `{activeThreadId}` est interpolé par Livewire
     * depuis la propriété du composant, si bien que changer de fil change d'abonnement.
     *
     * Rien à faire dans le corps : les messages viennent de propriétés calculées, et Livewire les
     * recalcule à chaque requête. Recevoir l'événement suffit à redessiner.
     */
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
