<?php

namespace App\Models;

use Database\Factories\ComplaintCaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property array $attachments
 * @property array $meta
 * @property ?Carbon $first_response_at
 * @property ?Carbon $due_at
 * @property ?Carbon $resolved_at
 * @property ?Carbon $closed_at
 * @property ?Carbon $escalated_at
 * @property ?Carbon $last_activity_at
 * @property bool $auto_resolved
 * @property int $escalation_level
 * @property ?string $reference
 * @property string $severity
 * @property ?int $provider_user_id
 * @property ?int $booking_id
 */
class ComplaintCase extends Model
{
    /** @use HasFactory<ComplaintCaseFactory> */
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

    /** @return BelongsTo<Booking, $this> */
    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rendez_vous_id');
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<User, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<DisputeEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(DisputeEvent::class)->orderBy('created_at');
    }

    /** @return HasMany<DisputeResolution, $this> */
    public function resolutions(): HasMany
    {
        return $this->hasMany(DisputeResolution::class);
    }

    /** @return HasOne<DisputeResolution, $this> */
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
