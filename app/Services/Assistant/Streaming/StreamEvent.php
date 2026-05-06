<?php

namespace App\Services\Assistant\Streaming;

/**
 * Phase 5.1 — Événement de streaming normalisé (provider-agnostic).
 *
 * Yieldé par AnthropicStreamingProvider::chatStream() au fur et à mesure
 * de la réception de la réponse SSE. Permet à la couche UI d'afficher
 * progressivement le texte token par token.
 *
 * Types :
 *   - start                : début de message (model, usage initial)
 *   - text_block_start     : début d'un bloc texte (index)
 *   - text_delta           : nouveau token texte
 *   - tool_use_start       : début d'un appel tool (id, name)
 *   - tool_input_delta     : chunk de JSON arguments du tool
 *   - content_block_stop   : fin d'un bloc
 *   - message_delta        : update du stop_reason et tokens
 *   - stop                 : fin du message complet
 *   - error                : erreur en cours de stream
 */
class StreamEvent
{
    public const TYPE_START               = 'start';
    public const TYPE_TEXT_BLOCK_START    = 'text_block_start';
    public const TYPE_TEXT_DELTA          = 'text_delta';
    public const TYPE_TOOL_USE_START      = 'tool_use_start';
    public const TYPE_TOOL_INPUT_DELTA    = 'tool_input_delta';
    public const TYPE_CONTENT_BLOCK_STOP  = 'content_block_stop';
    public const TYPE_MESSAGE_DELTA       = 'message_delta';
    public const TYPE_STOP                = 'stop';
    public const TYPE_ERROR               = 'error';

    private function __construct(
        public readonly string $type,
        public readonly array $payload = [],
    ) {}

    public function toArray(): array
    {
        return [
            'type'    => $this->type,
            'payload' => $this->payload,
        ];
    }

    // ──────────────────────────────────────────────────────
    // Factory methods (un par type, pour clarté)
    // ──────────────────────────────────────────────────────

    public static function start(?string $model, int $inputTokens = 0): self
    {
        return new self(self::TYPE_START, [
            'model'        => $model,
            'input_tokens' => $inputTokens,
        ]);
    }

    public static function textBlockStart(int $index): self
    {
        return new self(self::TYPE_TEXT_BLOCK_START, ['index' => $index]);
    }

    public static function textDelta(int $index, string $text): self
    {
        return new self(self::TYPE_TEXT_DELTA, [
            'index' => $index,
            'text'  => $text,
        ]);
    }

    public static function toolUseStart(int $index, string $toolUseId, string $toolName): self
    {
        return new self(self::TYPE_TOOL_USE_START, [
            'index'        => $index,
            'tool_use_id'  => $toolUseId,
            'tool_name'    => $toolName,
        ]);
    }

    public static function toolInputDelta(int $index, string $jsonChunk): self
    {
        return new self(self::TYPE_TOOL_INPUT_DELTA, [
            'index'      => $index,
            'json_chunk' => $jsonChunk,
        ]);
    }

    public static function contentBlockStop(int $index): self
    {
        return new self(self::TYPE_CONTENT_BLOCK_STOP, ['index' => $index]);
    }

    public static function messageDelta(?string $stopReason, int $outputTokens): self
    {
        return new self(self::TYPE_MESSAGE_DELTA, [
            'stop_reason'   => $stopReason,
            'output_tokens' => $outputTokens,
        ]);
    }

    public static function stop(): self
    {
        return new self(self::TYPE_STOP);
    }

    public static function error(string $message): self
    {
        return new self(self::TYPE_ERROR, ['message' => $message]);
    }
}
