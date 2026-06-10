<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSignature extends Model
{
    use HasFactory;

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

    /** @return BelongsTo<MissionQualityInspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(MissionQualityInspection::class, 'inspection_id');
    }

    /** @return BelongsTo<User, $this> */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }
}
