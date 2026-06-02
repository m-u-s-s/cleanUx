<?php

namespace App\Models;

use Database\Factories\IncidentReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentReport extends Model
{
    /** @use HasFactory<IncidentReportFactory> */
    use HasFactory;

    protected $fillable = [
        'rendez_vous_id',
        'employe_id',
        'client_id',
        'organization_account_id',
        'assigned_to',
        'type',
        'priority',
        'sla_policy',
        'severity',
        'status',
        'title',
        'description',
        'location_notes',
        'photos',
        'attachments',
        'meta',
        'first_response_at',
        'due_at',
        'resolution_notes',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'photos' => 'array',
        'attachments' => 'array',
        'meta' => 'array',
        'first_response_at' => 'datetime',
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /** @return BelongsTo<Booking, $this> */
    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rendez_vous_id');
    }

    /** @return BelongsTo<User, $this> */
    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
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

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast() && ! in_array($this->status, ['resolu', 'ferme'], true);
    }
}
