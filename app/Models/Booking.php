<?php

namespace App\Models;

use App\Models\Concerns\HasBookingDisplayAccessors;
use App\Support\Domain\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_reference',
        'recurring_booking_series_id',
        'series_position',

        'customer_user_id',
        'customer_organization_id',
        'organization_site_id',

        'service_catalog_id',
        'service_zone_id',
        'postal_code_id',

        'preferred_provider_user_id',
        'assigned_provider_organization_id',
        'assigned_provider_user_id',
        'provider_team_id',

        'scheduled_date',
        'scheduled_time',
        'booking_mode',
        'status',
        'priority',

        'place_type',
        'frequency',
        'surface_m2',

        'address',
        'city',
        'postal_code',
        'country',

        'contact_name',
        'contact_phone',
        'contact_email',

        'customer_comment',
        'internal_notes',

        'estimated_price',
        'estimated_duration_minutes',
        'currency',

        'options',
        'areas',
        'photos_reference',

        'pricing_snapshot',
        'zone_snapshot',
        'matching_snapshot',

        'created_by',
        'approved_by',
        'approved_at',

        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',

        /*
         * Temporary legacy compatibility.
         */
        'client_id',
        'employe_id',
        'date',
        'heure',
        'adresse',
        'ville',
        'code_postal',
        'telephone_client',
        'commentaire_client',
        'devis_estime',
        'duree_estimee',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime:H:i',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',

        'estimated_price' => 'decimal:2',
        'estimated_duration_minutes' => 'integer',

        'surface_m2' => 'integer',

        'options' => 'array',
        'areas' => 'array',
        'photos_reference' => 'array',
        'pricing_snapshot' => 'array',
        'zone_snapshot' => 'array',
        'matching_snapshot' => 'array',

        'date' => 'date',
        'devis_estime' => 'decimal:2',
        'duree_estimee' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function assignedProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_provider_user_id');
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function customerOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'customer_organization_id');
    }

    public function assignedProviderOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'assigned_provider_organization_id');
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

    public function postalCode(): BelongsTo
    {
        return $this->belongsTo(PostalCode::class);
    }

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    public function mission(): HasOne
    {
        return $this->hasOne(Mission::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function latestFeedback(): HasOne
    {
        return $this->hasOne(Feedback::class)->latestOfMany();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(BookingApproval::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BookingAttachment::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [
            BookingStatus::PENDING->value ?? 'pending',
            'pending',
            'pending_approval',
            'pending_assignment',
        ], true);
    }

    public function isConfirmed(): bool
    {
        return in_array($this->status, [
            BookingStatus::CONFIRMED->value ?? 'confirmed',
            'confirmed',
        ], true);
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, [
            BookingStatus::CANCELLED->value ?? 'cancelled',
            'cancelled',
        ], true);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [
            BookingStatus::COMPLETED->value ?? 'completed',
            'completed',
        ], true);
    }

    public function getDisplayAddressAttribute(): string
    {
        return $this->address
            ?? $this->adresse
            ?? '';
    }

    public function getDisplayCityAttribute(): string
    {
        return $this->city
            ?? $this->ville
            ?? '';
    }

    public function getDisplayPostalCodeAttribute(): string
    {
        return $this->postal_code
            ?? $this->code_postal
            ?? '';
    }

    public function getDisplayDateAttribute(): mixed
    {
        return $this->scheduled_date
            ?? $this->date;
    }

    public function getDisplayTimeAttribute(): mixed
    {
        return $this->scheduled_time
            ?? $this->heure;
    }
}