<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingStepCompletion extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'progress_id', 'step_id', 'status',
        'data', 'validator_payload',
        'last_error', 'attempt_count',
        'completed_at', 'completed_by_user_id', 'metadata',
    ];

    protected $casts = [
        'data' => 'array',
        'validator_payload' => 'array',
        'attempt_count' => 'integer',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function progress(): BelongsTo
    {
        return $this->belongsTo(OnboardingProgress::class, 'progress_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(OnboardingStep::class, 'step_id');
    }
}
