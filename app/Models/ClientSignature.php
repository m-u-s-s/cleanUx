<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSignature extends Model
{
    protected $fillable = [
        'inspection_id', 'signer_user_id',
        'signer_name', 'signer_email_hash',
        'signature_data', 'signed_at',
        'ip_hash', 'user_agent_short',
        'terms_version', 'metadata',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(MissionQualityInspection::class, 'inspection_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }
}
