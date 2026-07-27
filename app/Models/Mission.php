<?php

namespace App\Models;

use Database\Factories\MissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property ?Carbon $planned_start_at
 * @property ?Carbon $planned_end_at
 * @property ?Carbon $actual_start_at
 * @property ?Carbon $actual_end_at
 * @property bool $requires_start_code
 * @property bool $requires_end_code
 * @property bool $client_presence_confirmed
 * @property string $start_lat
 * @property string $start_lng
 * @property string $end_lat
 * @property string $end_lng
 * @property string $destination_lat
 * @property string $destination_lng
 * @property ?Carbon $client_final_validated_at
 * @property array $quality_summary
 * @property ?Carbon $sla_response_due_at
 * @property ?Carbon $sla_resolution_due_at
 */
class Mission extends Model
{
    /** @use HasFactory<MissionFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'rendez_vous_id',
        'organization_account_id',
        'provider_organization_id',
        'provider_team_id',
        'organization_site_id',
        'service_catalog_id',
        'service_zone_id',
        'lead_employee_id',
        'lead_provider_user_id',
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

        'last_eta_meters',
        'last_eta_seconds',
        'last_eta_source',
        'last_eta_calculated_at',

        // SP4 — contract & SLA
        'organization_contract_id',
        'sla_response_due_at',
        'sla_resolution_due_at',
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
        'sla_response_due_at' => 'datetime',
        'sla_resolution_due_at' => 'datetime',
    ];

    /** @return BelongsTo<Booking, $this> */
    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rendez_vous_id');
    }

    /**
     * Alias de rendezVous() — la nomenclature historique stocke la FK
     * dans `rendez_vous_id`, mais le code Phase 11+ utilise `$mission->booking`
     * (cohérent avec la table `bookings`). Cette relation pointe sur la
     * même colonne pour éviter de toucher à la migration.
     *
     * IMPORTANT : sans cette relation, `$mission->booking` retournait `null`
     * et toute la chaîne dispatch / ETA / paiement était silencieusement cassée.
     */
    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        $fk = $this->booking_id ? 'booking_id' : 'rendez_vous_id';

        return $this->belongsTo(Booking::class, $fk);
    }

    /**
     * Relations dédiées à l'eager loading.
     *
     * booking() choisit sa FK depuis $this->booking_id, ce que Laravel ne peut pas faire en
     * eager load : il résout la relation sur une instance vierge, où l'attribut est toujours
     * vide, et retombe donc systématiquement sur rendez_vous_id. Or les deux colonnes portent
     * un bookings.id selon le chemin de création (CreateBookingFromApiAction écrit booking_id,
     * MissionFromRendezVousSyncService écrit rendez_vous_id). D'où deux relations explicites,
     * que l'appelant charge ensemble et combine — sans toucher au comportement de booking().
     */
    /** @return BelongsTo<Booking, $this> */
    public function bookingViaBookingId(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /** @return BelongsTo<Booking, $this> */
    public function bookingViaRendezVous(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rendez_vous_id');
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class);
    }

    /** @return BelongsTo<OrganizationContract, $this> */
    public function organizationContract(): BelongsTo
    {
        return $this->belongsTo(OrganizationContract::class, 'organization_contract_id');
    }

    /**
     * @return BelongsTo<OrganizationAccount, $this>
     */
    public function providerOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'provider_organization_id');
    }

    /** @return BelongsTo<OrganizationSite, $this> */
    public function organizationSite(): BelongsTo
    {
        return $this->belongsTo(OrganizationSite::class);
    }

    /**
     * Alias du site de la mission (clé : organization_site_id). Le DispatchCenter
     * société charge `bookingSite` et la vue lit `$mission->bookingSite?->name/city`.
     *
     * @return BelongsTo<OrganizationSite, $this>
     */
    public function bookingSite(): BelongsTo
    {
        return $this->belongsTo(OrganizationSite::class, 'organization_site_id');
    }

    /** @return BelongsTo<ServiceCatalog, $this> */
    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }

    /** @return BelongsTo<ServiceZone, $this> */
    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    /** @return BelongsTo<User, $this> */
    public function leadEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_employee_id');
    }

    /**
     * Phase 11+ — Le lead "prestataire" d'une mission. Distinct de leadEmployee
     * historique (interne à une org) car un prestataire peut être indépendant.
     * Le code Phase 11+ écrit dans `lead_provider_user_id` à l'acceptation
     * d'une offre via MissionDispatchService::accept().
     *
     * @return BelongsTo<User, $this>
     */
    public function leadProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_provider_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /** @return HasMany<MissionAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(MissionAssignment::class);
    }

    /** @return HasMany<MissionVerificationCode, $this> */
    public function verificationCodes(): HasMany
    {
        return $this->hasMany(MissionVerificationCode::class);
    }

    /** @return HasMany<MissionTrackingSession, $this> */
    public function trackingSessions(): HasMany
    {
        return $this->hasMany(MissionTrackingSession::class);
    }

    /** @return HasOne<MissionClientAction, $this> */
    public function activeTrackingSession(): HasOne
    {
        return $this->hasOne(MissionTrackingSession::class)
            ->where('is_active', true)
            ->latestOfMany();
    }

    /** @return HasMany<MissionClientAction, $this> */
    public function clientActions(): HasMany
    {
        return $this->hasMany(MissionClientAction::class);
    }

    /** @return HasMany<MissionChecklist, $this> */
    public function checklists(): HasMany
    {
        return $this->hasMany(MissionChecklist::class);
    }

    /** @return HasMany<MissionTaskSegment, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(MissionMedia::class);
    }

    /** @return HasMany<MissionTaskSegment, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(MissionIncident::class);
    }

    /** @return HasMany<MissionTaskSegment, $this> */
    public function qualityReviews(): HasMany
    {
        return $this->hasMany(MissionQualityReview::class);
    }

    /** @return HasOne<MissionTaskSegment, $this> */
    public function report(): HasOne
    {
        return $this->hasOne(MissionReport::class);
    }

    /** @return HasMany<MissionTaskSegment, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(MissionEvent::class)->orderBy('happened_at');
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    /** @return BelongsTo<MissionTaskSegment, $this> */
    public function taskSegment(): BelongsTo
    {
        return $this->belongsTo(MissionTaskSegment::class, 'mission_task_segment_id');
    }
}
