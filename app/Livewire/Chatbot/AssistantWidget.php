<?php

namespace App\Livewire\Chatbot;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Services\Assistant\Tools\AssistantToolDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Phase 5.2 — AssistantWidget enrichi avec streaming UI.
 *
 * Différences avec Phase 5 :
 *   - send() ne lance plus directement LlmClient (qui bloquait Livewire jusqu'à
 *     la fin de la réponse). À la place, il :
 *       1. Persiste le message utilisateur
 *       2. Génère une URL signée vers /assistant/stream
 *       3. Dispatche un browser event 'assistant:stream-start'
 *     Le JS prend le relais avec EventSource pour afficher en temps réel.
 *   - Nouveau handler streamCompleted() appelé quand le JS finit de streamer
 *     pour rafraîchir la liste des messages persistés.
 *
 * Le mode "non-streaming" reste disponible (fallback) via $useStreaming = false.
 */
class AssistantWidget extends Component
{
    public bool   $isOpen          = false;
    public bool   $isLoading       = false;
    public string $input           = '';
    public bool   $useStreaming    = true; // si false, fallback Phase 5 sync
    public ?int   $conversationId  = null;
    public ?int   $pendingActionId = null;

    /** @var array<int, array{sender:string, content:string, time:string, message_id?:int}> */
    public array $messages = [];

    public function mount(): void
    {
        $user = Auth::user();
        $conversation = AssistantConversation::query()
            ->where('user_id', $user->id)
            ->where('status', AssistantConversation::STATUS_OPEN)
            ->latest()
            ->first();

        if ($conversation) {
            $this->conversationId = $conversation->id;
            $this->loadHistory($conversation);
        } else {
            $this->messages = [[
                'sender'  => 'assistant',
                'content' => $this->welcomeMessage($user),
                'time'    => now()->format('H:i'),
            ]];
        }
    }

    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;
    }

    /**
     * Phase 5.2 — Envoi avec streaming.
     *
     * Au lieu d'appeler LlmClient (bloquant), on :
     *   1. Persiste le message user
     *   2. Génère une URL signée
     *   3. Dispatche au front pour qu'il ouvre un EventSource
     */
    public function send(): void
    {
        $message = trim($this->input);
        if (blank($message) || $this->isLoading) {
            return;
        }

        $user = Auth::user();

        // Rate limit côté serveur (paranoïa : le middleware ne tape pas Livewire actions)
        if ($this->isRateLimited($user)) {
            $this->messages[] = [
                'sender'  => 'assistant',
                'content' => "⏱ Tu as atteint la limite de messages. Réessaye dans quelques minutes.",
                'time'    => now()->format('H:i'),
            ];
            return;
        }

        // Render immédiat du message user
        $this->messages[] = [
            'sender'  => 'user',
            'content' => $message,
            'time'    => now()->format('H:i'),
        ];
        $this->input     = '';
        $this->isLoading = true;

        $conversation = $this->getOrCreateConversation($user);
        $userMessage  = AssistantMessage::create([
            'assistant_conversation_id' => $conversation->id,
            'sender_type'               => AssistantMessage::SENDER_USER,
            'content'                   => $message,
        ]);

        if ($this->useStreaming) {
            $signedUrl = URL::temporarySignedRoute(
                'assistant.stream',
                now()->addMinutes(5),
                [
                    'conversation_id'   => $conversation->id,
                    'user_message_id'   => $userMessage->id,
                ]
            );

            // Dispatch un event navigateur — le JS dans la blade ouvrira l'EventSource.
            $this->dispatch('assistant:stream-start', [
                'url'             => $signedUrl,
                'conversation_id' => $conversation->id,
                'user_message_id' => $userMessage->id,
            ]);
        } else {
            // Fallback sync (Phase 5 — comportement original)
            $this->sendSync($user, $conversation, $message);
        }
    }

    /**
     * Appelé par le JS quand le stream est terminé.
     * On reload la liste des messages depuis la DB pour récupérer le message
     * assistant final persisté + d'éventuels tool_uses qui ont créé des
     * AssistantAction en pending_confirmation.
     */
    #[On('assistant:stream-completed')]
    public function streamCompleted(?int $messageId = null, bool $hasTools = false): void
    {
        $this->isLoading = false;

        $user = Auth::user();
        $conversation = $this->conversationId
            ? AssistantConversation::find($this->conversationId)
            : null;

        if (! $conversation) {
            return;
        }

        $this->loadHistory($conversation);

        // Si un tool_use a créé une action en attente, recharger l'ID pour afficher le bouton confirm
        if ($hasTools) {
            $latestAction = \App\Models\AssistantAction::query()
                ->where('assistant_conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->where('status', \App\Models\AssistantAction::STATUS_PENDING_CONFIRMATION)
                ->latest('id')
                ->first();

            if ($latestAction) {
                $this->pendingActionId = $latestAction->id;
            }
        }
    }

    #[On('assistant:stream-error')]
    public function streamError(?string $message = null): void
    {
        $this->isLoading = false;
        $this->messages[] = [
            'sender'  => 'assistant',
            'content' => "❌ Erreur de streaming : " . ($message ?: "connexion interrompue"),
            'time'    => now()->format('H:i'),
        ];
    }

    public function confirmAction(int $actionId): void
    {
        $user   = Auth::user();
        $result = app(AssistantToolDispatcher::class)->confirmAndExecute($user, $actionId);

        if (! empty($result['ok'])) {
            $resPayload = $result['result'] ?? [];
            $msg = $resPayload['message'] ?? "Action exécutée avec succès.";
            $this->messages[] = [
                'sender'  => 'assistant',
                'content' => "✅ " . $msg,
                'time'    => now()->format('H:i'),
            ];
        } else {
            $this->messages[] = [
                'sender'  => 'assistant',
                'content' => "❌ " . ($result['error'] ?? "L'action n'a pas pu être exécutée."),
                'time'    => now()->format('H:i'),
            ];
        }

        $this->pendingActionId = null;
    }

    public function cancelAction(int $actionId): void
    {
        $user = Auth::user();
        app(AssistantToolDispatcher::class)->cancel($user, $actionId);

        $this->messages[] = [
            'sender'  => 'assistant',
            'content' => "Action annulée.",
            'time'    => now()->format('H:i'),
        ];
        $this->pendingActionId = null;
    }

    public function clearConversation(): void
    {
        if ($this->conversationId) {
            AssistantConversation::find($this->conversationId)
                ?->update(['status' => AssistantConversation::STATUS_ARCHIVED]);
        }

        $this->conversationId  = null;
        $this->pendingActionId = null;
        $user = Auth::user();

        $this->messages = [[
            'sender'  => 'assistant',
            'content' => $this->welcomeMessage($user),
            'time'    => now()->format('H:i'),
        ]];
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    private function isRateLimited($user): bool
    {
        $perHour = (int) config('services.assistant.rate_per_hour', 30);
        $count = \App\Models\AssistantApiLog::query()
            ->forUser($user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        return $count >= $perHour;
    }

    private function loadHistory(AssistantConversation $conversation): void
    {
        $this->messages = AssistantMessage::query()
            ->where('assistant_conversation_id', $conversation->id)
            ->whereIn('sender_type', [
                AssistantMessage::SENDER_USER,
                AssistantMessage::SENDER_ASSISTANT,
            ])
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->map(fn (AssistantMessage $m) => [
                'sender'     => $m->sender_type,
                'content'    => $m->content !== '' ? $m->content : '(action en cours…)',
                'time'       => $m->created_at->format('H:i'),
                'message_id' => $m->id,
            ])
            ->values()
            ->toArray();
    }

    private function getOrCreateConversation($user): AssistantConversation
    {
        if ($this->conversationId) {
            $conversation = AssistantConversation::find($this->conversationId);
            if ($conversation && $conversation->isOpen()) {
                return $conversation;
            }
        }

        $conversation = AssistantConversation::create([
            'user_id'                  => $user->id,
            'organization_account_id'  => $user->organization_account_id,
            'context_role'             => $user->assistantContextRole()->value,
            'status'                   => AssistantConversation::STATUS_OPEN,
        ]);

        $this->conversationId = $conversation->id;
        return $conversation;
    }

    /**
     * Fallback sync (mode Phase 5 — bloquant) si streaming désactivé.
     */
    private function sendSync($user, AssistantConversation $conversation, string $message): void
    {
        try {
            $result = app(\App\Services\Assistant\Llm\LlmClient::class)
                ->sendUserMessage($user, $conversation, $message);

            $this->messages[] = [
                'sender'  => 'assistant',
                'content' => $result['text'],
                'time'    => now()->format('H:i'),
            ];
            $this->pendingActionId = $result['pending_action_id'] ?? null;
        } catch (\Throwable $e) {
            report($e);
            $this->messages[] = [
                'sender'  => 'assistant',
                'content' => "Une erreur est survenue. Reformule ta question s'il te plaît.",
                'time'    => now()->format('H:i'),
            ];
        }
        $this->isLoading = false;
    }

    private function welcomeMessage($user): string
    {
        $name = $user->name;
        $role = $user->assistantContextRole();

        return match ($role->value) {
            'client_personal' => "Bonjour {$name} 👋 Je peux t'aider à réserver, suivre une mission, ou expliquer une facture. Que veux-tu faire ?",
            'client_company'  => "Bonjour {$name} 👋 Demande une intervention pour un de tes locaux, vois les missions actives, ou explique-moi une facture.",
            'provider_independent' => "Bonjour {$name} 👋 Tes missions, paiements Stripe, incidents — je suis là pour ça.",
            'provider_company' => "Bonjour {$name} 👋 Missions du jour, canaux d'équipe, signalement d'incident — comment je peux aider ?",
            'admin'           => "Bonjour {$name} — assistant admin CleanUx prêt.",
            default           => "Bonjour {$name} ! Comment puis-je vous aider ?",
        };
    }

    public function render()
    {
        return view('livewire.chatbot.assistant-widget');
    }
}
