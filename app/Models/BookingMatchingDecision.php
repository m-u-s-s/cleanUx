<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingMatchingDecision extends Model
{
    protected $fillable = [
        'booking_id',
        'selected_user_id',
        'candidates_count',
        'selected_score',
        'top_score',
        'runner_up_score',
        'algorithm_version',
        'strategy',
        'weights_snapshot',
        'candidates_breakdown',
        'selected_breakdown',
        'metadata',
    ];

    protected $casts = [
        'candidates_count' => 'integer',
        'selected_score' => 'decimal:2',
        'top_score' => 'decimal:2',
        'runner_up_score' => 'decimal:2',
        'weights_snapshot' => 'array',
        'candidates_breakdown' => 'array',
        'selected_breakdown' => 'array',
        'metadata' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function selectedProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_user_id');
    }
}
