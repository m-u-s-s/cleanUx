<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnterpriseBookingApproval extends Model
{
    protected $fillable = [
        'rendez_vous_id',
        'organization_account_id',
        'organization_site_id',
        'requested_by_user_id',
        'manager_approved_by_user_id',
        'finance_approved_by_user_id',
        'status',
        'request_note',
        'manager_note',
        'finance_note',
        'rejection_reason',
        'manager_approved_at',
        'finance_approved_at',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'manager_approved_at' => 'datetime',
        'finance_approved_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rendez_vous_id');
    }

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class);
    }

    public function organizationSite(): BelongsTo
    {
        return $this->belongsTo(OrganizationSite::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function managerApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by_user_id');
    }

    public function financeApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_approved_by_user_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending_manager' => 'En attente manager',
            'pending_finance' => 'En attente finance',
            'approved' => 'Approuvé',
            'rejected' => 'Refusé',
            'cancelled' => 'Annulé',
            default => ucfirst($this->status),
        };
    }
}
