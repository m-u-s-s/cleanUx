<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessBeneficialOwner extends Model
{
    public const AML_PENDING = 'pending';
    public const AML_CLEAR = 'clear';
    public const AML_FLAGGED = 'flagged';
    public const AML_REVIEW = 'review';

    protected $fillable = [
        'entity_id', 'full_name', 'date_of_birth',
        'country_of_residence', 'nationality',
        'ownership_percent', 'is_director', 'is_pep', 'is_sanctioned',
        'aml_status', 'metadata',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'ownership_percent' => 'float',
        'is_director' => 'boolean',
        'is_pep' => 'boolean',
        'is_sanctioned' => 'boolean',
        'metadata' => 'array',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class, 'entity_id');
    }

    public function scopeFlagged(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->where('is_pep', true)->orWhere('is_sanctioned', true));
    }
}
