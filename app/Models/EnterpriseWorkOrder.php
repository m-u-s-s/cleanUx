<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnterpriseWorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_account_id',
        'organization_site_id',
        'organization_contract_id',
        'service_catalog_id',
        'service_zone_id',
        'requested_by_user_id',
        'assigned_field_team_id',
        'assigned_service_partner_id',
        'title',
        'reference',
        'status',
        'priority',
        'approval_status',
        'work_type',
        'requested_start_at',
        'requested_end_at',
        'scheduled_start_at',
        'scheduled_end_at',
        'purchase_order_number',
        'cost_center',
        'budget_amount',
        'instructions',
        'metadata',
    ];

    protected $casts = [
        'requested_start_at' => 'datetime',
        'requested_end_at' => 'datetime',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'budget_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class);
    }

    public function organizationSite(): BelongsTo
    {
        return $this->belongsTo(OrganizationSite::class);
    }

    public function organizationContract(): BelongsTo
    {
        return $this->belongsTo(OrganizationContract::class);
    }

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }

    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function assignedFieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class, 'assigned_field_team_id');
    }

    public function assignedServicePartner(): BelongsTo
    {
        return $this->belongsTo(ServicePartner::class, 'assigned_service_partner_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(WorkOrderApproval::class);
    }

    public function missionBatches(): HasMany
    {
        return $this->hasMany(MissionBatch::class);
    }

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }
}
