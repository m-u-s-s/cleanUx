<?php

namespace App\Services\Assistant\Tools;

use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\User;
use Throwable;

/**
 * Dispatcher : exécute un tool_use décidé par le LLM.
 *
 * 2 chemins possibles :
 *   A. Tool en lecture (executesImmediately = true) → exécute direct
 *   B. Tool en écriture → crée une AssistantAction status=pending_confirmation
 *      et renvoie au LLM un payload "needs_user_confirmation" pour qu'il
 *      explique à l'utilisateur et lui propose le bouton "Confirmer".
 *
 * Le UI Livewire fournit un bouton "Confirmer" qui appelle
 *   confirmAndExecute(int $assistantActionId)
 * qui finalise l'exécution.
 */
class AssistantToolDispatcher
{
    public function __construct(
        protected AssistantToolRegistry $registry
    ) {}

    /**
     * Dispatche un tool_use brut tel que reçu de l'API LLM.
     *
     * @param array $toolUse Format Anthropic: ['id' => 'toolu_xxx', 'name' => '...', 'input' => [...]]
     * @return array Payload à renvoyer à l'API en tool_result.
     */
    public function dispatch(
        User $user,
        AssistantConversation $conversation,
        array $toolUse,
    ): array {
        $name  = $toolUse['name']  ?? '';
        $input = $toolUse['input'] ?? [];

        $tool = $this->registry->find($name);

        if (! $tool) {
            return [
                'ok'    => false,
                'error' => "Tool '{$name}' inconnu.",
            ];
        }

        if (! $tool->authorize($user)) {
            return [
                'ok'    => false,
                'error' => "Vous n'êtes pas autorisé à utiliser cette action.",
            ];
        }

        // Path A — exécution immédiate (lecture)
        if ($tool->executesImmediately()) {
            try {
                return $tool->execute($user, $input);
            } catch (Throwable $e) {
                report($e);
                return [
                    'ok'    => false,
                    'error' => "Erreur durant l'exécution : " . $e->getMessage(),
                ];
            }
        }

        // Path B — création d'une AssistantAction en attente de confirmation
        $action = AssistantAction::create([
            'assistant_conversation_id' => $conversation->id,
            'user_id'                   => $user->id,
            'action_type'               => $tool->name(),
            'status'                    => AssistantAction::STATUS_PENDING_CONFIRMATION,
            'payload'                   => [
                'tool_input'  => $input,
                'tool_use_id' => $toolUse['id'] ?? null,
            ],
        ]);

        return [
            'ok'                       => true,
            'needs_user_confirmation'  => true,
            'assistant_action_id'      => $action->id,
            'action_type'              => $tool->name(),
            'human_readable_payload'   => $input,
            'message'                  => "Action préparée. L'utilisateur doit cliquer 'Confirmer' dans l'interface.",
        ];
    }

    /**
     * Confirme et exécute une AssistantAction qui était en attente.
     * Appelé depuis l'UI quand l'utilisateur clique "Confirmer".
     *
     * @return array Résultat d'exécution (à renvoyer ensuite au LLM si on veut continuer la conversation).
     */
    public function confirmAndExecute(User $user, int $assistantActionId): array
    {
        $action = AssistantAction::query()
            ->where('id', $assistantActionId)
            ->where('user_id', $user->id)
            ->first();

        if (! $action) {
            return ['ok' => false, 'error' => "Action introuvable ou non autorisée."];
        }

        if (! $action->isPending()) {
            return [
                'ok'    => false,
                'error' => "Cette action n'est plus en attente (statut: {$action->status}).",
            ];
        }

        $tool = $this->registry->find($action->action_type);
        if (! $tool) {
            $action->markFailed("Tool '{$action->action_type}' n'existe plus dans le registry.");
            return ['ok' => false, 'error' => "Action obsolète."];
        }

        if (! $tool->authorize($user)) {
            $action->markFailed("Autorisation refusée à la confirmation.");
            return ['ok' => false, 'error' => "Vous n'êtes plus autorisé à exécuter cette action."];
        }

        $action->markConfirmed();

        try {
            $input  = $action->payload['tool_input'] ?? [];
            $result = $tool->execute($user, $input);
            $action->markExecuted($result);

            return ['ok' => true, 'result' => $result];

        } catch (Throwable $e) {
            report($e);
            $action->markFailed($e->getMessage());

            return ['ok' => false, 'error' => "Échec d'exécution : " . $e->getMessage()];
        }
    }

    public function cancel(User $user, int $assistantActionId): array
    {
        $action = AssistantAction::query()
            ->where('id', $assistantActionId)
            ->where('user_id', $user->id)
            ->first();

        if (! $action) {
            return ['ok' => false, 'error' => "Action introuvable."];
        }

        $action->update(['status' => AssistantAction::STATUS_CANCELLED]);

        return ['ok' => true, 'message' => "Action annulée."];
    }
}
