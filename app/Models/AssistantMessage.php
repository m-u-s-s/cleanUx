<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message individuel dans une conversation assistant.
 * sender_type : user | assistant | system | tool_result
 */
class AssistantMessage extends Model
{
    use HasFactory;

    public const SENDER_USER        = 'user';
    public const SENDER_ASSISTANT   = 'assistant';
    public const SENDER_SYSTEM      = 'system';
    public const SENDER_TOOL_RESULT = 'tool_result';

    protected $fillable = [
        'assistant_conversation_id',
        'sender_type',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }

    public function isFromUser(): bool
    {
        return $this->sender_type === self::SENDER_USER;
    }

    public function isFromAssistant(): bool
    {
        return $this->sender_type === self::SENDER_ASSISTANT;
    }

    /**
     * Format ce message pour l'API Anthropic Messages.
     * Si le message embarque un tool_use ou tool_result dans metadata,
     * structure le content en blocks (au lieu d'une string).
     */
    public function toApiPayload(): array
    {
        $role = match ($this->sender_type) {
            self::SENDER_USER, self::SENDER_TOOL_RESULT => 'user',
            self::SENDER_ASSISTANT => 'assistant',
            default => null,
        };

        if ($role === null) {
            return [];
        }

        // Cas tool_result : metadata contient tool_use_id
        if ($this->sender_type === self::SENDER_TOOL_RESULT && isset($this->metadata['tool_use_id'])) {
            return [
                'role' => 'user',
                'content' => [[
                    'type'         => 'tool_result',
                    'tool_use_id'  => $this->metadata['tool_use_id'],
                    'content'      => $this->content,
                    'is_error'     => (bool) ($this->metadata['is_error'] ?? false),
                ]],
            ];
        }

        // Cas assistant avec tool_use : metadata contient tool_uses
        if ($this->sender_type === self::SENDER_ASSISTANT && isset($this->metadata['tool_uses'])) {
            $blocks = [];
            if (! empty($this->content)) {
                $blocks[] = ['type' => 'text', 'text' => $this->content];
            }
            foreach ($this->metadata['tool_uses'] as $use) {
                $blocks[] = [
                    'type'  => 'tool_use',
                    'id'    => $use['id'],
                    'name'  => $use['name'],
                    'input' => $use['input'] ?? [],
                ];
            }
            return ['role' => 'assistant', 'content' => $blocks];
        }

        // Cas standard
        return ['role' => $role, 'content' => $this->content];
    }
}
