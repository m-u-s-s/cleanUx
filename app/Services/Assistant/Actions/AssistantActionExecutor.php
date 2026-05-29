<?php

namespace App\Services\Assistant\Actions;

use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\User;
use App\Support\ActivityLogger;

/**
 * Public facade for assistant action execution.
 *
 * Delegates read logic to AssistantReadActions and write logic to
 * AssistantWriteActions.  The public API (execute / executeWrite /
 * confirmAction / isWriteAction / executeImmediateWrite) is unchanged.
 *
 * Sub-services are injected via the constructor; when called without
 * arguments (e.g. in tests) they are instantiated directly.
 */
class AssistantActionExecutor
{
    /** Write actions that require a confirmation round-trip before execution. */
    private const WRITE_ACTIONS = [
        'create_booking',
        'cancel_booking',
        'resolve_dispute',
    ];

    private readonly AssistantReadActions $readActions;

    private readonly AssistantWriteActions $writeActions;

    public function __construct(
        ?AssistantReadActions $readActions = null,
        ?AssistantWriteActions $writeActions = null,
    ) {
        $this->readActions = $readActions ?? new AssistantReadActions;
        $this->writeActions = $writeActions ?? new AssistantWriteActions;
    }

    /**
     * Dispatch a read-only action by name and return its formatted French text.
     */
    public function execute(string $actionName, User $user, array $params = []): string
    {
        return $this->readActions->execute($actionName, $user, $params);
    }

    /**
     * Prepare a write action: persist a pending AssistantAction record and
     * return a confirmation payload the assistant can show to the user.
     *
     * @return array{action: string, requires_confirmation: bool, summary: string, params: array, action_id: int}
     */
    public function executeWrite(
        string $actionName,
        User $user,
        array $params = [],
        ?AssistantConversation $conversation = null,
    ): array {
        $summary = $this->writeActions->buildConfirmationSummary($actionName, $user, $params);

        $record = AssistantAction::create([
            'assistant_conversation_id' => $conversation?->id,
            'user_id' => $user->id,
            'action_type' => $actionName,
            'status' => AssistantAction::STATUS_PENDING_CONFIRMATION,
            'payload' => $params,
        ]);

        return [
            'action' => $actionName,
            'requires_confirmation' => true,
            'summary' => $summary,
            'params' => $params,
            'action_id' => $record->id,
        ];
    }

    /**
     * Confirm and execute a previously prepared write action.
     * Returns a human-readable French result string.
     */
    public function confirmAction(int $actionId, User $user): string
    {
        $action = AssistantAction::query()
            ->where('id', $actionId)
            ->where('user_id', $user->id)
            ->where('status', AssistantAction::STATUS_PENDING_CONFIRMATION)
            ->first();

        if (! $action) {
            return 'Action introuvable, expirée ou déjà exécutée.';
        }

        $action->markConfirmed();

        $result = $this->dispatchWrite($action->action_type, $user, $action->payload ?? []);

        if (str_starts_with($result, 'Erreur')) {
            $action->markFailed($result);
        } else {
            $action->markExecuted(['result_text' => $result]);
        }

        ActivityLogger::log('assistant.action_executed', $action, [
            'action_name' => $action->action_type,
            'user_id' => $user->id,
        ]);

        return $result;
    }

    /**
     * Returns whether an action name is a write action requiring confirmation.
     */
    public function isWriteAction(string $actionName): bool
    {
        return in_array($actionName, self::WRITE_ACTIONS, true);
    }

    /**
     * Execute a low-risk write action immediately (no confirmation round-trip).
     * Currently handles: update_availability.
     */
    public function executeImmediateWrite(string $actionName, User $user, array $params = []): string
    {
        return $this->dispatchWrite($actionName, $user, $params);
    }

    // ──────────────────────────────────────────────────────
    // Internal write dispatcher — delegates to AssistantWriteActions
    // ──────────────────────────────────────────────────────

    private function dispatchWrite(string $actionName, User $user, array $params): string
    {
        return $this->writeActions->dispatch($actionName, $user, $params);
    }
}
