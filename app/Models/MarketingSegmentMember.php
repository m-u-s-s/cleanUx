<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingSegmentMember extends Model
{
    protected $fillable = ['segment_id', 'user_id', 'computed_at', 'score', 'metadata'];

    protected $casts = [
        'computed_at' => 'datetime',
        'score' => 'decimal:4',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<MarketingSegment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(MarketingSegment::class, 'segment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
