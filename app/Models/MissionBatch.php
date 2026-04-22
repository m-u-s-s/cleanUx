<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MissionBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_account_id',
        'organization_site_id',
        'enterprise_work_order_id',
        'field_team_id',
        'service_partner_id',
        'name',
        'reference',
        'status',
        'batch_type',
        'starts_on',
        'ends_on',
        'default_start_time',
        'default_end_time',
        'estimated_total_minutes',
        'estimated_total_cost',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'estimated_total_minutes' => 'integer',
        'estimated_total_cost' => 'decimal:2',
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

    public function enterpriseWorkOrder(): BelongsTo
    {
        return $this->belongsTo(EnterpriseWorkOrder::class);
    }

    public function fieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class);
    }

    public function servicePartner(): BelongsTo
    {
        return $this->belongsTo(ServicePartner::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(MissionBatchDay::class)->orderBy('service_date');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(MissionTaskSegment::class)->orderBy('service_date')->orderBy('sequence');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'planned', 'active', 'in_progress']);
    }
}
