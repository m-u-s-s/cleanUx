<?php

namespace App\Services\Assistant\Llm;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Assistant\Tools\AssistantToolDispatcher;
use App\Services\Assistant\Tools\AssistantToolRegistry;
use App\Services\AssistantContextBuilder;

/**
 * Orchestrateur de l'assistant.
 *
 * Reçoit un message utilisateur et :
 *   1. Construit le contexte via AssistantContextBuilder
 *   2. Récupère les tools autorisés via AssistantToolRegistry
 *   3. Appelle le LlmProvider
 *   4. Si la réponse contient des tool_uses, les dispatche
 *      et boucle (max 5 itérations) jusqu'à obtenir une réponse texte finale
 *   5. Persiste tous les messages dans assistant_messages
 */
class LlmClient
{
    private const MAX_TOOL_ITERATIONS = 5;

    public function __construct(
        protected LlmProvider $provider,
        protected AssistantContextBuilder $contextBuilder,
        protected AssistantToolRegistry $toolRegistry,
        protected AssistantToolDispatcher $toolDispatcher,
    ) {}

    /**
     * Envoie un message utilisateur et retourne la réponse finale (texte) du LLM.
     * Persiste tous les messages de la boucle dans la conversation.
     *
     * @return array{text:string, has_pending_action:bool, pending_action_id:?int}
     */
    public function sendUserMessage(
        User $user,
        AssistantConversation $conversation,
        string $userMessage,
    ): array {
        // 1. Persister le message user
        AssistantMessage::create([
            'assistant_conversation_id' => $conversation->id,
            'sender_type'               => AssistantMessage::SENDER_USER,
            'content'                   => $userMessage,
        ]);

        // 2. Construire le contexte
        $context = $this->contextBuilder->build($user);
        $tools   = $this->toolRegistry->definitionsForUser($user);

        // 3. Charger l'historique (max 20 derniers messages utiles)
        $messages = $this->buildApiMessages($conversation);

        // 4. Boucle agentique
        $pendingActionId = null;
        $finalText       = '';

        for ($iteration = 0; $iteration < self::MAX_TOOL_ITERATIONS; $iteration++) {
            $response = $this->provider->chat($context['system'], $messages, $tools);

            if ($response->isError()) {
                $finalText = "Désolé, le service assistant rencontre un problème : " . ($response->error ?? 'erreur inconnue');
                break;
            }

            // Persister la réponse du LLM (avec ses tool_uses si présents)
            $assistantMsg = AssistantMessage::create([
                'assistant_conversation_id' => $conversation->id,
                'sender_type'               => AssistantMessage::SENDER_ASSISTANT,
                'content'                   => $response->text,
                'metadata'                  => $response->hasToolUses()
                    ? ['tool_uses' => $response->toolUses, 'usage' => $response->usage]
                    : ['usage' => $response->usage],
            ]);

            // Si pas de tool_use → réponse finale, on sort
            if (! $response->hasToolUses()) {
                $finalText = $response->text;
                break;
            }

            // Ajouter le message assistant (avec tool_use blocks) à l'historique pour le prochain tour
            $messages[] = $assistantMsg->toApiPayload();

            // Pour chaque tool_use, dispatcher et fabriquer un tool_result
            $toolResultBlocks = [];

            foreach ($response->toolUses as $toolUse) {
                $result = $this->toolDispatcher->dispatch($user, $conversation, $toolUse);

                // Si le tool a créé une AssistantAction en attente, on retient l'ID
                // pour que l'UI affiche le bouton "Confirmer"
                if (! empty($result['needs_user_confirmation']) && ! empty($result['assistant_action_id'])) {
                    $pendingActionId = (int) $result['assistant_action_id'];
                }

                $toolResultBlocks[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $toolUse['id'],
                    'content'     => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'is_error'    => empty($result['ok']) && isset($result['error']),
                ];

                // Persister le tool_result
                AssistantMessage::create([
                    'assistant_conversation_id' => $conversation->id,
                    'sender_type'               => AssistantMessage::SENDER_TOOL_RESULT,
                    'content'                   => json_encode($result, JSON_UNESCAPED_UNICODE),
                    'metadata'                  => [
                        'tool_use_id' => $toolUse['id'],
                        'tool_name'   => $toolUse['name'],
                        'is_error'    => empty($result['ok']) && isset($result['error']),
                    ],
                ]);
            }

            // Ajouter les tool_results comme un seul message user à l'historique
            $messages[] = [
                'role'    => 'user',
                'content' => $toolResultBlocks,
            ];

            // Boucle continue : le LLM va recevoir les résultats et générer une vraie réponse texte
        }

        if ($finalText === '') {
            $finalText = "L'assistant a atteint la limite de tours d'exécution. Reformule ta demande s'il te plaît.";
        }

        return [
            'text'                  => $finalText,
            'has_pending_action'    => $pendingActionId !== null,
            'pending_action_id'     => $pendingActionId,
        ];
    }

    /**
     * Reconstruit l'historique d'une conversation au format API.
     * Filtre system messages et limite à 20 messages les plus récents.
     */
    private function buildApiMessages(AssistantConversation $conversation): array
    {
        $rows = AssistantMessage::query()
            ->where('assistant_conversation_id', $conversation->id)
            ->whereIn('sender_type', [
                AssistantMessage::SENDER_USER,
                AssistantMessage::SENDER_ASSISTANT,
                AssistantMessage::SENDER_TOOL_RESULT,
            ])
            ->orderBy('id')
            ->get()
            ->reverse()
            ->take(20)
            ->reverse()
            ->values();

        return $rows
            ->map(fn (AssistantMessage $m) => $m->toApiPayload())
            ->filter(fn ($p) => ! empty($p))
            ->values()
            ->all();
    }
}
