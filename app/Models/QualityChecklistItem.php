<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityChecklistItem extends Model
{
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_RATING = 'rating';
    public const TYPE_TEXT = 'text';
    public const TYPE_PHOTO = 'photo';
    public const TYPE_MEASUREMENT = 'measurement';
    public const TYPE_SELECT = 'select';

    protected $fillable = [
        'checklist_id', 'position', 'code', 'label', 'description',
        'item_type', 'required', 'weight',
        'valid_options', 'expected_value', 'metadata',
    ];

    protected $casts = [
        'position' => 'integer',
        'required' => 'boolean',
        'weight' => 'integer',
        'valid_options' => 'array',
        'expected_value' => 'array',
        'metadata' => 'array',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(QualityChecklist::class, 'checklist_id');
    }
}
