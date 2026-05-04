<?php

namespace App\Livewire\Chatbot;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Services\AssistantContextBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;
use Livewire\Component;

class AssistantWidget extends Component
{
    // ──────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────
    public bool   $isOpen    = false;
    public bool   $isLoading = false;
    public string $input     = '';

    /** @var array<int, array{sender: string, content: string, time: string}> */
    public array $messages = [];

    public ?int $conversationId = null;

    // ──────────────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────────────
    public function mount(): void
    {
        $user = Auth::user();

        // Reprendre la dernière conversation ouverte ou en créer une
        $conversation = AssistantConversation::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest()
            ->first();

        if ($conversation) {
            $this->conversationId = $conversation->id;

            // Charger les 20 derniers messages
            $this->messages = AssistantMessage::query()
                ->where('assistant_conversation_id', $conversation->id)
                ->latest()
                ->limit(20)
                ->get()
                ->reverse()
                ->map(fn ($m) => [
                    'sender'  => $m->sender_type,
                    'content' => $m->content,
                    'time'    => $m->created_at->format('H:i'),
                ])
                ->values()
                ->toArray();
        } else {
            // Nouveau message de bienvenue selon le rôle
            $this->messages = [[
                'sender'  => 'assistant',
                'content' => $this->welcomeMessage($user),
                'time'    => now()->format('H:i'),
            ]];
        }
    }

    // ──────────────────────────────────────────────────────
    // Actions
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

        $user = Auth::user();

        // Ajouter le message utilisateur à l'UI immédiatement
        $this->messages[] = [
            'sender'  => 'user',
            'content' => $message,
            'time'    => now()->format('H:i'),
        ];

        $this->input     = '';
        $this->isLoading = true;

        // Persister la conversation si première fois
        if (! $this->conversationId) {
            $conversation = AssistantConversation::create([
                'user_id'                  => $user->id,
                'organization_account_id'  => $user->current_organization_id,
                'context_role'             => $user->assistantContextRole()->value,
                'status'                   => 'open',
            ]);

            $this->conversationId = $conversation->id;
        }

        // Persister le message utilisateur
        AssistantMessage::create([
            'assistant_conversation_id' => $this->conversationId,
            'sender_type'               => 'user',
            'content'                   => $message,
        ]);

        // Appeler l'API
        $response = $this->callAnthropicApi($user, $message);

        // Ajouter la réponse du bot
        $this->messages[] = [
            'sender'  => 'assistant',
            'content' => $response,
            'time'    => now()->format('H:i'),
        ];

        // Persister la réponse
        AssistantMessage::create([
            'assistant_conversation_id' => $this->conversationId,
            'sender_type'               => 'assistant',
            'content'                   => $response,
        ]);

        $this->isLoading = false;
    }

    public function clearConversation(): void
    {
        if ($this->conversationId) {
            AssistantConversation::find($this->conversationId)?->update(['status' => 'archived']);
        }

        $this->conversationId = null;
        $user = Auth::user();

        $this->messages = [[
            'sender'  => 'assistant',
            'content' => $this->welcomeMessage($user),
            'time'    => now()->format('H:i'),
        ]];
    }

    // ──────────────────────────────────────────────────────
    // API Anthropic
    // ──────────────────────────────────────────────────────
    private function callAnthropicApi($user, string $userMessage): string
    {
        try {
            $context = app(AssistantContextBuilder::class)->build($user);

            // Construire l'historique de conversation pour l'API
            $history = collect($this->messages)
                ->filter(fn ($m) => in_array($m['sender'], ['user', 'assistant'], true))
                ->map(fn ($m) => [
                    'role'    => $m['sender'] === 'user' ? 'user' : 'assistant',
                    'content' => $m['content'],
                ])
                ->values()
                ->toArray();

            $response = Http::withHeaders([
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-sonnet-4-20250514',
                'max_tokens' => 800,
                'system'     => $context['system'],
                'messages'   => $history,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['content'][0]['text'] ?? 'Je n\'ai pas pu générer une réponse.';
            }

            return 'Une erreur est survenue. Veuillez réessayer dans quelques instants.';

        } catch (\Throwable $e) {
            report($e);
            return 'Le service est temporairement indisponible.';
        }
    }

    // ──────────────────────────────────────────────────────
    // Messages de bienvenue par rôle
    // ──────────────────────────────────────────────────────
    private function welcomeMessage($user): string
    {
        $name = $user->name;
        $role = $user->assistantContextRole();

        return match ($role) {
            default => "Bonjour {$name} ! 👋 Je suis votre assistant CleanUx. Comment puis-je vous aider ?",
        };
    }

    // ──────────────────────────────────────────────────────
    // Render
    // ──────────────────────────────────────────────────────
    public function render()
    {
        return view('livewire.chatbot.assistant-widget');
    }
}
