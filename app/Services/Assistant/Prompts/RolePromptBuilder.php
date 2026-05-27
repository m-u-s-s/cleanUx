<?php

namespace App\Services\Assistant\Prompts;

use App\Models\User;
use App\Services\AssistantContextBuilder;

/**
 * Thin facade that returns the role-specific system prompt for a user.
 *
 * The full prompt construction (role detection + dynamic context enrichment)
 * lives in AssistantContextBuilder, which is already used by LlmClient and
 * AssistantStreamController. This class is the public entry point for callers
 * that only need the prompt string (e.g. the Livewire widget on mount).
 */
class RolePromptBuilder
{
    public function __construct(
        private readonly AssistantContextBuilder $contextBuilder,
    ) {}

    public function buildSystemPrompt(User $user): string
    {
        return $this->contextBuilder->build($user)['system'];
    }
}
