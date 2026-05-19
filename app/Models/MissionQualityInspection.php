<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MissionQualityInspection extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VALIDATED_CLIENT = 'validated_client';
    public const STATUS_VALIDATED_ADMIN = 'validated_admin';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'mission_id', 'booking_id', 'checklist_id', 'phase', 'status',
        'submitted_by_user_id', 'submitted_at',
        'validated_by_user_id', 'validated_at',
        'score_calculated', 'score_max',
        'dispute_reason', 'disputed_at',
        'idempotency_key', 'metadata',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
        'disputed_at' => 'datetime',
        'score_calculated' => 'decimal:2',
        'score_max' => 'integer',
        'metadata' => 'array',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(QualityChecklist::class, 'checklist_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class, 'inspection_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InspectionPhoto::class, 'inspection_id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ClientSignature::class, 'inspection_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }

    public function scorePercent(): ?float
    {
        if (! $this->score_max || $this->score_calculated === null) {
            return null;
        }
        return round((float) $this->score_calculated / (float) $this->score_max * 100, 2);
    }

    public function scopeForMission(Builder $q, int $missionId): Builder
    {
        return $q->where('mission_id', $missionId);
    }

    public function scopePendingValidation(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_SUBMITTED);
    }
}
