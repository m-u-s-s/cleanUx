<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NpsResponse extends Model
{
    public const CATEGORY_DETRACTOR = 'detractor';   // 0-6
    public const CATEGORY_PASSIVE = 'passive';       // 7-8
    public const CATEGORY_PROMOTER = 'promoter';     // 9-10

    protected $fillable = [
        'user_id', 'booking_id', 'survey_code', 'score', 'category',
        'comment', 'locale', 'metadata', 'responded_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'metadata' => 'array',
        'responded_at' => 'datetime',
    ];

    public static function categorize(int $score): string
    {
        if ($score <= 6) return self::CATEGORY_DETRACTOR;
        if ($score <= 8) return self::CATEGORY_PASSIVE;
        return self::CATEGORY_PROMOTER;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
