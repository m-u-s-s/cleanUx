<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractSignatureAudit extends Model
{
    public const EVENT_VIEW = 'view';
    public const EVENT_SENT = 'sent';
    public const EVENT_OPENED = 'opened';
    public const EVENT_SIGNED = 'signed';
    public const EVENT_INVALIDATED = 'invalidated';

    protected $fillable = [
        'signature_id', 'document_id', 'user_id',
        'event', 'ip_hash', 'user_agent_short',
        'occurred_at', 'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(ContractDocument::class);
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(ContractSignature::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
