<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Channel extends Model
{
    protected $fillable = [
        'organization_account_id',
        'mission_id',
        'booking_id',
        'name',
        'type',
        'is_private',
        'is_locked',
        'is_archived',
        'archived_at',
        'archived_by',
        'created_by',
        'settings',
    ];

    protected $casts = [
        'is_private'   => 'boolean',
        'is_locked'    => 'boolean',
        'is_archived'  => 'boolean',
        'archived_at'  => 'datetime',
        'settings'     => 'array',
    ];

    // Types disponibles
    public const TYPE_TEAM         = 'team';
    public const TYPE_MISSION      = 'mission';
    public const TYPE_SUPPORT      = 'support';
    public const TYPE_PRIVATE      = 'private';
    public const TYPE_ANNOUNCEMENT = 'announcement';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_members')
            ->withPivot(['role', 'last_read_at'])
            ->withTimestamps();
    }

    public function scopeForOrg($query, int $orgId)
    {
        return $query->where('organization_account_id', $orgId);
    }

    public function unreadCountFor(User $user): int
    {
        $pivot = $this->members()
            ->where('user_id', $user->id)
            ->first()?->pivot;

        if (! $pivot?->last_read_at) {
            return $this->messages()->count();
        }

        return $this->messages()
            ->where('created_at', '>', $pivot->last_read_at)
            ->where('user_id', '!=', $user->id)
            ->count();
    }

    public function markReadFor(User $user): void
    {
        $this->members()->updateExistingPivot($user->id, [
            'last_read_at' => now(),
        ]);
    }

    public function lastMessage(): ?Message
    {
        return $this->messages()->latest()->first();
    }
}
