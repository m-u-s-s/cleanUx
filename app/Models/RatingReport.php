<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatingReport extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWED_KEPT = 'reviewed_kept';
    public const STATUS_REVIEWED_HIDDEN = 'reviewed_hidden';
    public const STATUS_DISMISSED = 'dismissed';

    public const REASON_SPAM = 'spam';
    public const REASON_OFFENSIVE = 'offensive';
    public const REASON_FAKE = 'fake';
    public const REASON_IRRELEVANT = 'irrelevant';
    public const REASON_PERSONAL_INFO = 'discloses_personal_info';
    public const REASON_HARASSMENT = 'harassment';
    public const REASON_OTHER = 'other';

    protected $fillable = [
        'feedback_id',
        'reporter_user_id',
        'reason',
        'details',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'admin_note',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
