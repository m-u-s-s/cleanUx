<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionItem extends Model
{
    protected $fillable = [
        'inspection_id', 'checklist_item_id',
        'value', 'photos_count', 'comment',
        'met', 'score_awarded',
        'recorded_by_user_id', 'recorded_at', 'metadata',
    ];

    protected $casts = [
        'value' => 'array',
        'photos_count' => 'integer',
        'met' => 'boolean',
        'score_awarded' => 'integer',
        'recorded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(MissionQualityInspection::class, 'inspection_id');
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(QualityChecklistItem::class, 'checklist_item_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InspectionPhoto::class, 'inspection_item_id');
    }
}
