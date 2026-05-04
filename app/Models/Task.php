<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    protected $fillable = [
        'organization_account_id',
        'channel_id',
        'created_by',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
        'metadata'     => 'array',
    ];

    // Statuts
    public const STATUS_TODO       = 'todo';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE       = 'done';
    public const STATUS_CANCELLED  = 'cancelled';

    // Priorités
    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    // Scopes
    public function scopeTodo($query)        { return $query->where('status', self::STATUS_TODO); }
    public function scopeInProgress($query)  { return $query->where('status', self::STATUS_IN_PROGRESS); }
    public function scopeDone($query)        { return $query->where('status', self::STATUS_DONE); }

    public function scopeForOrg($query, int $orgId)
    {
        return $query->where('organization_account_id', $orgId);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->whereHas('assignees', fn ($q) => $q->where('user_id', $userId));
    }

    // Helpers
    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && $this->status !== self::STATUS_DONE;
    }

    public function priorityColor(): string
    {
        return match ($this->priority) {
            self::PRIORITY_URGENT => 'red',
            self::PRIORITY_HIGH   => 'orange',
            self::PRIORITY_MEDIUM => 'blue',
            default               => 'slate',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_TODO        => 'À faire',
            self::STATUS_IN_PROGRESS => 'En cours',
            self::STATUS_DONE        => 'Terminé',
            self::STATUS_CANCELLED   => 'Annulé',
            default                  => $this->status,
        };
    }
}
