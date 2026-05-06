<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantApiLog extends Model
{
    use HasFactory;

    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR   = 'error';
    public const STATUS_TIMEOUT = 'timeout';

    protected $fillable = [
        'user_id',
        'assistant_conversation_id',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'cost_usd',
        'latency_ms',
        'status',
        'stop_reason',
        'error_message',
        'tool_use_count',
        'tools_used',
    ];

    protected $casts = [
        'cost_usd'       => 'decimal:6',
        'tools_used'     => 'array',
        'input_tokens'   => 'integer',
        'output_tokens'  => 'integer',
        'total_tokens'   => 'integer',
        'latency_ms'     => 'integer',
        'tool_use_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeSinceDays(Builder $q, int $days): Builder
    {
        return $q->where('created_at', '>=', now()->subDays($days));
    }
}
