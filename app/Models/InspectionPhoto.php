<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionPhoto extends Model
{
    public const TYPE_BEFORE = 'before';
    public const TYPE_DURING = 'during';
    public const TYPE_AFTER = 'after';
    public const TYPE_DEFECT = 'defect';
    public const TYPE_SIGNATURE_PROOF = 'signature_proof';

    protected $fillable = [
        'inspection_id', 'inspection_item_id',
        'photo_path', 'photo_type',
        'uploaded_by_user_id', 'uploaded_at', 'ip_hash', 'metadata',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(MissionQualityInspection::class, 'inspection_id');
    }
}
