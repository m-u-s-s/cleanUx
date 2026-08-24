<?php

namespace App\Models;

use Database\Factories\OrganizationSiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ?string $latitude
 * @property ?string $longitude
 * @property ?int $surface_m2
 * @property ?int $floor_count
 * @property ?array $metadata
 * @property ?array $trade_preferences
 */
class OrganizationSite extends Model
{
    /** @use HasFactory<OrganizationSiteFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_account_id',
        'name',
        'address',
        'address_line_1',
        'address_line_2',
        'service_zone_id',
        'postal_code_id',
        'city',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'surface_m2',
        'floor_count',
        'access_instructions',
        'contact_name',
        'contact_phone',
        'contact_email',
        'preferred_provider_id',
        'cleaning_frequency',   // legacy — renamed to service_frequency in migration A2
        'service_frequency',    // multi-trade generalisation
        'trade_preferences',    // JSON: per-trade booking preferences
        'preferred_time_slot',
        'status',
        'notes',
        'metadata',

        // ÉCRITES PAR LE CODE, ÉCARTÉES PAR ELOQUENT. Ces colonnes existent en base et des
        // appels d'écriture les renseignent, mais leur absence de cette liste les faisait
        // disparaître SANS ERREUR — Eloquent écarte en silence ce qu'il ne peut pas assigner.
        'client_user_id',
        'site_code',
        'email',
        'phone',
        'is_primary',
        'is_active',
        'type',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'surface_m2' => 'integer',
        'floor_count' => 'integer',
        'metadata' => 'array',
        'trade_preferences' => 'array',
    ];

    // Fréquences
    public const FREQ_ONE_TIME = 'one_time';

    public const FREQ_WEEKLY = 'weekly';

    public const FREQ_BIWEEKLY = 'biweekly';

    public const FREQ_MONTHLY = 'monthly';

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function preferredProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_provider_id');
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'organization_site_id');
    }

    /**
     * Les référents désignés SUR ce site par des sociétés prestataires.
     *
     * @return HasMany<ProviderSiteAssignment, $this>
     */
    public function providerAssignments(): HasMany
    {
        return $this->hasMany(ProviderSiteAssignment::class, 'organization_site_id');
    }

    /** @return BelongsTo<ServiceZone, $this> */
    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'service_zone_id');
    }

    /** @return BelongsTo<PostalCode, $this> */
    public function postalCode(): BelongsTo
    {
        return $this->belongsTo(PostalCode::class, 'postal_code_id');
    }

    /** @return BelongsToMany<OrganizationMember, $this> */
    public function authorizedMembers(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganizationMember::class,
            'organization_member_site_access',
            'organization_site_id',
            'organization_member_id'
        )->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForOrg($query, ?int $orgId)
    {
        if ($orgId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('organization_account_id', $orgId);
    }

    // Helpers
    public function fullAddress(): string
    {
        return "{$this->address}, {$this->postal_code} {$this->city}";
    }

    public function frequencyLabel(): string
    {
        // After migration A2, the column is service_frequency.
        // Fall back to cleaning_frequency for backward compatibility.
        $freq = $this->service_frequency ?? $this->cleaning_frequency ?? null;

        return match ($freq) {
            self::FREQ_ONE_TIME => 'Ponctuel',
            self::FREQ_WEEKLY => 'Hebdomadaire',
            self::FREQ_BIWEEKLY => 'Bi-mensuel',
            self::FREQ_MONTHLY => 'Mensuel',
            default => 'Non defini',
        };
    }

    public function activeBookingsCount(): int
    {
        return $this->bookings()
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->count();
    }

    protected static function booted(): void
    {
        static::saving(function (OrganizationSite $site) {
            if (blank($site->address) && filled($site->address_line_1)) {
                $site->address = $site->address_line_1;
            }

            if (blank($site->address) && filled($site->name)) {
                $site->address = trim($site->name.' '.$site->postal_code.' '.$site->city);
            }

            if (blank($site->address)) {
                $site->address = 'Adresse non renseignée';
            }

            if (blank($site->address_line_1) && filled($site->address)) {
                $site->address_line_1 = $site->address;
            }
        });
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    /** @return BelongsTo<PostalCode, $this> */
    public function postalCodeReference(): BelongsTo
    {
        return $this->belongsTo(PostalCode::class, 'postal_code_id');
    }
}
