<?php

namespace App\Livewire\Chatbot;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Services\Assistant\Llm\LlmClient;
use App\Services\Assistant\Tools\AssistantToolDispatcher;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Phase 5 — AssistantWidget refactoré.
 *
 * Avant : appelait directement Http::post à l'API Anthropic, pas de tools.
 * Après : délègue à LlmClient (multi-provider), supporte le tool calling
 *         agentic, gère le workflow de confirmation pour les actions
 *         destructives (create_booking, cancel_booking…).
 *
 * Différences clés :
 *   - Plus de logique Http directe : tout passe par LlmClient
 *   - Si une action nécessite confirmation, $pendingActionId est set et
 *     le UI affiche un bouton "Confirmer" qui appelle confirmAction().
 */
class AssistantWidget extends Component
{
    // ──────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────
    public bool   $isOpen    = false;
    public bool   $isLoading = false;
    public string $input     = '';

    /** @var array<int, array{sender:string, content:string, time:string, action_id?:int}> */
    public array $messages = [];

    public ?int $conversationId = null;

    /** Si non-null, le UI affichera un bouton "Confirmer cette action". */
    public ?int $pendingActionId = null;

    // ──────────────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────────────
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
            ->map(fn(AssistantMessage $m) => [
                'sender'  => $m->sender_type,
                'content' => $m->content !== '' ? $m->content : '(action en cours…)',
                'time'    => $m->created_at->format('H:i'),
            ])
            ->values()
            ->toArray();
    }

    // ──────────────────────────────────────────────────────
    // Actions Livewire
    // ──────────────────────────────────────────────────────
    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;
    }

    public function send(): void
    {
        $message = trim($this->input);
        if (blank($message) || $this->isLoading) {
            return;
        }

        // Rate limit côté Livewire
        $apiLog = \App\Models\AssistantApiLog::query()
            ->forUser(Auth::id())
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($apiLog >= (int) config('services.assistant.rate_per_hour', 30)) {
            $this->messages[] = [
                'sender'  => 'assistant',
                'content' => "⏱ Tu as atteint la limite de messages par heure. Réessaye dans un instant.",
                'time'    => now()->format('H:i'),
            ];
            return;
        }

        $user = Auth::user();

        // Rendu instantané du message user
        $this->messages[] = [
            'sender'  => 'user',
            'content' => $message,
            'time'    => now()->format('H:i'),
        ];
        $this->input     = '';
        $this->isLoading = true;

        // Récupère ou crée la conversation
        $conversation = $this->getOrCreateConversation($user);

        // Délégation à LlmClient (qui gère la boucle agentic)
        try {
            $result = app(LlmClient::class)->sendUserMessage($user, $conversation, $message);

            $this->messages[] = [
                'sender'    => 'assistant',
                'content'   => $result['text'],
                'time'      => now()->format('H:i'),
                'action_id' => $result['pending_action_id'] ?? null,
            ];

            $this->pendingActionId = $result['pending_action_id'] ?? null;
        } catch (\Throwable $e) {
            report($e);
            $this->messages[] = [
                'sender'  => 'assistant',
                'content' => "Une erreur inattendue est survenue. Reformule ta question s'il te plaît.",
                'time'    => now()->format('H:i'),
            ];
        }

        $this->isLoading = false;
    }

    public function confirmAction(int $actionId): void
    {
        $user = Auth::user();

        $result = app(AssistantToolDispatcher::class)->confirmAndExecute($user, $actionId);

        if (! empty($result['ok'])) {
            $resPayload = $result['result'] ?? [];
            $msg = $resPayload['message']
                ?? "Action exécutée avec succès.";

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

    private function welcomeMessage($user): string
    {
        $name = $user->name;
        $role = $user->assistantContextRole();

        return match ($role->value) {
            'client_personal' => "Bonjour {$name} 👋 Je peux t'aider à réserver un nettoyage, suivre une mission, ou comprendre une facture. Que veux-tu faire ?",
            'client_company'  => "Bonjour {$name} 👋 Je peux préparer une demande pour un de tes locaux, te lister les missions actives ou expliquer une facture. Comment puis-je t'aider ?",
            'provider_independent' => "Bonjour {$name} 👋 Je peux t'aider avec tes missions, tes paiements Stripe ou un incident sur site. Quelle est ta question ?",
            'provider_company' => "Bonjour {$name} 👋 Je peux te montrer tes missions du jour, t'expliquer les canaux d'équipe ou signaler un incident. Que veux-tu ?",
            'admin'           => "Bonjour {$name} — assistant admin CleanUx prêt. Pose ta question (stats, anomalies, configuration…).",
            default           => "Bonjour {$name} ! Comment puis-je vous aider ?",
        };
    }

    public function render()
    {
        return view('livewire.chatbot.assistant-widget');
    }
}
