<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractDocument extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_SIGNATURE = 'pending_signature';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'template_id', 'code', 'user_id',
        'body_rendered_html', 'pdf_path', 'status',
        'generated_at', 'expires_at', 'metadata',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ContractSignature::class, 'document_id');
    }

    public function activeSignature(): ?ContractSignature
    {
        return $this->signatures()->where('is_invalidated', false)->latest('signed_at')->first();
    }

    public function scopePendingSignature(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING_SIGNATURE);
    }
}
