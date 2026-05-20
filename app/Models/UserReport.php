<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserReport extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_RESOLVED_ACTION = 'resolved_action_taken';
    public const STATUS_RESOLVED_NO_ACTION = 'resolved_no_action';
    public const STATUS_DISMISSED = 'dismissed';

    public const CATEGORIES = [
        'harassment' => 'Harcèlement / menaces',
        'fraud' => 'Fraude / arnaque',
        'inappropriate_content' => 'Contenu inapproprié',
        'safety_concern' => 'Préoccupation sécurité',
        'spam' => 'Spam / publicité non sollicitée',
        'other' => 'Autre',
    ];

    protected $fillable = [
        'code', 'reporter_user_id', 'reported_user_id',
        'category', 'description', 'evidence',
        'status', 'reviewed_by_admin_id', 'admin_notes', 'reviewed_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public static function generateCode(): string
    {
        return 'rpt_' . Str::lower(Str::random(20));
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function reported(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }
}
