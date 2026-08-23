<?php

namespace App\Services\Assistant\Llm;

/** DTO de réponse LLM normalisée (indépendant du provider). */
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
