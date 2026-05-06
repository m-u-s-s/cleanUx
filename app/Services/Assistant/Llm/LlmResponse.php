<?php

namespace App\Services\Assistant\Llm;

/**
 * DTO de réponse LLM normalisée (indépendant du provider).
 *
 * stop_reason : 'end_turn' | 'tool_use' | 'max_tokens' | 'stop_sequence' | 'error'
 *
 * tool_uses : si stop_reason = 'tool_use', contient les blocks tool_use que
 *   le LLM veut exécuter, format :
 *   [
 *     ['id' => 'toolu_xxx', 'name' => 'create_booking', 'input' => [...]],
 *   ]
 */
class LlmResponse
{
    public function __construct(
        public readonly string $text,
        public readonly string $stopReason,
        public readonly array $toolUses = [],
        public readonly array $usage = [],
        public readonly ?string $error = null,
    ) {}

    public function hasToolUses(): bool
    {
        return $this->stopReason === 'tool_use' && ! empty($this->toolUses);
    }

    public function isError(): bool
    {
        return $this->stopReason === 'error' || $this->error !== null;
    }

    public static function error(string $message): self
    {
        return new self(
            text: '',
            stopReason: 'error',
            error: $message,
        );
    }
}
