<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Action initiée par l'assistant et requérant confirmation utilisateur
 * avant exécution.
 *
 * Workflow : pending_confirmation → confirmed → executed
 *                                 ↘ cancelled
 *                                 ↘ failed
 */
class AssistantAction extends Model
{
    use HasFactory;

    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';
    public const STATUS_CONFIRMED            = 'confirmed';
    public const STATUS_EXECUTED             = 'executed';
    public const STATUS_CANCELLED            = 'cancelled';
    public const STATUS_FAILED               = 'failed';

    protected $fillable = [
        'assistant_conversation_id',
        'user_id',
        'action_type',
        'status',
        'payload',
        'confirmed_at',
        'executed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'confirmed_at' => 'datetime',
        'executed_at'  => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markConfirmed(): void
    {
        $this->update([
            'status'       => self::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
    }

    public function markExecuted(?array $resultPayload = null): void
    {
        $payload = $this->payload;
        if ($resultPayload !== null) {
            $payload['result'] = $resultPayload;
        }

        $this->update([
            'status'      => self::STATUS_EXECUTED,
            'executed_at' => now(),
            'payload'     => $payload,
        ]);
    }

    public function markFailed(string $reason): void
    {
        $payload = $this->payload;
        $payload['failure_reason'] = $reason;

        $this->update([
            'status'  => self::STATUS_FAILED,
            'payload' => $payload,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING_CONFIRMATION;
    }
}
