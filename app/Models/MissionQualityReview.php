<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionQualityReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'reviewer_user_id',
        'reviewer_role',
        'final_status',
        'score',
        'cleanliness_score',
        'punctuality_score',
        'behavior_score',
        'comment',
        'reviewed_at',
        'meta',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}