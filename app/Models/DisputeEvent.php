<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeEvent extends Model
{
    public const TYPE_OPENED = 'opened';
    public const TYPE_MESSAGE = 'message';
    public const TYPE_ADMIN_MESSAGE = 'admin_message';
    public const TYPE_PROVIDER_RESPONSE = 'provider_response';
    public const TYPE_STATUS_CHANGED = 'status_changed';
    public const TYPE_ASSIGNED = 'assigned';
    public const TYPE_ESCALATED = 'escalated';
    public const TYPE_SLA_WARNING = 'sla_warning';
    public const TYPE_RESOLVED = 'resolved';
    public const TYPE_CLOSED = 'closed';
    public const TYPE_REOPENED = 'reopened';
    public const TYPE_ATTACHMENT_ADDED = 'attachment_added';
    public const TYPE_NOTE = 'note';

    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_CLIENT = 'client';
    public const VISIBILITY_PROVIDER = 'provider';
    public const VISIBILITY_ALL = 'all';

    public const ROLE_CLIENT = 'client';
    public const ROLE_PROVIDER = 'provider';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SYSTEM = 'system';

    protected $fillable = [
        'complaint_case_id',
        'type',
        'visibility',
        'author_user_id',
        'author_role',
        'body',
        'attachments',
        'payload',
        'from_status',
        'to_status',
    ];

    protected $casts = [
        'attachments' => 'array',
        'payload' => 'array',
    ];

    public function complaintCase(): BelongsTo
    {
        return $this->belongsTo(ComplaintCase::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function isVisibleTo(?string $viewerRole): bool
    {
        if ($this->visibility === self::VISIBILITY_ALL) {
            return $viewerRole !== null;
        }
        if ($this->visibility === self::VISIBILITY_PRIVATE) {
            return $viewerRole === self::ROLE_ADMIN;
        }
        return $this->visibility === $viewerRole || $viewerRole === self::ROLE_ADMIN;
    }

    public function scopeVisibleTo(Builder $q, string $viewerRole): Builder
    {
        if ($viewerRole === self::ROLE_ADMIN) {
            return $q;
        }
        return $q->where(function (Builder $sub) use ($viewerRole) {
            $sub->where('visibility', self::VISIBILITY_ALL)
                ->orWhere('visibility', $viewerRole);
        });
    }
}
