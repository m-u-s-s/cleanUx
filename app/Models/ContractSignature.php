<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractSignature extends Model
{
    protected $fillable = [
        'document_id', 'signer_user_id',
        'signer_name', 'signer_email_hash',
        'signature_data', 'signature_hash',
        'ip_hash', 'user_agent_short',
        'terms_version', 'country_code', 'geolocation',
        'signed_at', 'expires_at',
        'is_invalidated', 'invalidated_by_user_id',
        'invalidated_at', 'invalidation_reason',
        'metadata',
    ];

    protected $casts = [
        'geolocation' => 'array',
        'is_invalidated' => 'boolean',
        'signed_at' => 'datetime',
        'expires_at' => 'datetime',
        'invalidated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(ContractDocument::class, 'document_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }

    public function invalidator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invalidated_by_user_id');
    }

    public function isValid(): bool
    {
        if ($this->is_invalidated) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    public function scopeValid(Builder $q): Builder
    {
        return $q->where('is_invalidated', false)
            ->where(function ($w) {
                $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
