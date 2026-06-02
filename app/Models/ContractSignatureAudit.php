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

    /** @return BelongsTo<ContractDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ContractDocument::class);
    }

    /** @return BelongsTo<ContractSignature, $this> */
    public function signature(): BelongsTo
    {
        return $this->belongsTo(ContractSignature::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
