<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ComplaintCase extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_AWAITING_CLIENT = 'awaiting_client';
    public const STATUS_AWAITING_PROVIDER = 'awaiting_provider';
    public const STATUS_INVESTIGATING = 'investigating';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_ESCALATED = 'escalated';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    public const CATEGORY_QUALITY = 'quality';
    public const CATEGORY_NO_SHOW = 'no_show';
    public const CATEGORY_PAYMENT = 'payment';
    public const CATEGORY_DAMAGE = 'damage';
    public const CATEGORY_SAFETY = 'safety';
    public const CATEGORY_COMMUNICATION = 'communication';
    public const CATEGORY_OTHER = 'other';

    public const FINAL_STATUSES = [self::STATUS_RESOLVED, self::STATUS_CLOSED];

    protected $fillable = [
        'reference',
        'rendez_vous_id',
        'booking_id',
        'client_id',
        'organization_account_id',
        'provider_user_id',
        'assigned_to',
        'category',
        'priority',
        'severity',
        'sla_policy',
        'resolution_category',
        'status',
        'subject',
        'description',
        'attachments',
        'admin_response',
        'first_response_at',
        'due_at',
        'meta',
        'resolved_at',
        'closed_at',
        'escalation_level',
        'escalated_at',
        'auto_resolved',
        'last_activity_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'meta' => 'array',
        'first_response_at' => 'datetime',
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'escalated_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'auto_resolved' => 'boolean',
        'escalation_level' => 'integer',
    ];

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rendez_vous_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DisputeEvent::class)->orderBy('created_at');
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(DisputeResolution::class);
    }

    public function appliedResolution(): HasOne
    {
        return $this->hasOne(DisputeResolution::class)
            ->where('status', DisputeResolution::STATUS_APPLIED)
            ->latestOfMany('applied_at');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && ! $this->isFinal();
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true)
            || in_array($this->status, ['resolu', 'ferme'], true);
    }

    public function isActive(): bool
    {
        return ! $this->isFinal();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNotIn('status', array_merge(self::FINAL_STATUSES, ['resolu', 'ferme']));
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->active()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }

    public function scopeByCategory(Builder $q, string $category): Builder
    {
        return $q->where('category', $category);
    }
}
