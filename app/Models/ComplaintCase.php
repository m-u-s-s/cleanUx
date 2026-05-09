<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'rendez_vous_id',
        'client_id',
        'organization_account_id',
        'assigned_to',
        'category',
        'priority',
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
    ];

    protected $casts = [
        'attachments' => 'array',
        'meta' => 'array',
        'first_response_at' => 'datetime',
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rendez_vous_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast() && ! in_array($this->status, ['resolu', 'ferme'], true);
    }
}
