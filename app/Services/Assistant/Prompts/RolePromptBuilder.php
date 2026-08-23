<?php

namespace App\Services\Assistant\Prompts;

use App\Models\User;
use App\Services\AssistantContextBuilder;

/** Thin facade that returns the role-specific system prompt for a user. */
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
