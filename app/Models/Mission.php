<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mission extends Model
{
    use HasFactory;

    protected $fillable = [
        'rendez_vous_id',
        'organization_account_id',
        'organization_site_id',
        'service_catalog_id',
        'service_zone_id',
        'lead_employee_id',
        'status',
        'mission_type',
        'planned_start_at',
        'planned_end_at',
        'actual_start_at',
        'actual_end_at',
        'requires_start_code',
        'requires_end_code',
        'client_presence_confirmed',
        'started_by_user_id',
        'closed_by_user_id',
        'start_lat',
        'start_lng',
        'end_lat',
        'end_lng',
        'notes',
        'destination_lat',
        'destination_lng',
        'quality_score',
        'quality_status',
        'client_final_status',
        'client_final_validated_at',
        'quality_summary',
        'employee_cost',
        'client_price',
        'margin',
        'actual_duration_minutes',
        'travel_duration_minutes',
    ];

    protected $casts = [
        'planned_start_at' => 'datetime',
        'planned_end_at' => 'datetime',
        'actual_start_at' => 'datetime',
        'actual_end_at' => 'datetime',
        'requires_start_code' => 'boolean',
        'requires_end_code' => 'boolean',
        'client_presence_confirmed' => 'boolean',
        'start_lat' => 'decimal:7',
        'start_lng' => 'decimal:7',
        'end_lat' => 'decimal:7',
        'end_lng' => 'decimal:7',
        'destination_lat' => 'decimal:7',
        'destination_lng' => 'decimal:7',
        'client_final_validated_at' => 'datetime',
        'quality_summary' => 'array',
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

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }

    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    public function leadEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_employee_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MissionAssignment::class);
    }

    public function verificationCodes(): HasMany
    {
        return $this->hasMany(MissionVerificationCode::class);
    }

    public function trackingSessions(): HasMany
    {
        return $this->hasMany(MissionTrackingSession::class);
    }

    public function activeTrackingSession(): HasOne
    {
        return $this->hasOne(MissionTrackingSession::class)
            ->where('is_active', true)
            ->latestOfMany();
    }

    public function clientActions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MissionClientAction::class);
    }

    public function checklists(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MissionChecklist::class);
    }

    public function media(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MissionMedia::class);
    }

    public function incidents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MissionIncident::class);
    }

    public function qualityReviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MissionQualityReview::class);
    }

    public function report(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MissionReport::class);
    }

    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MissionEvent::class)->orderBy('happened_at');
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    public function taskSegment(): BelongsTo
    {
        return $this->belongsTo(MissionTaskSegment::class, 'mission_task_segment_id');
    }
}
