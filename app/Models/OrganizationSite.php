<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrganizationSite extends Model
{
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
        'cleaning_frequency',
        'preferred_time_slot',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'latitude'    => 'decimal:7',
        'longitude'   => 'decimal:7',
        'surface_m2'  => 'integer',
        'floor_count' => 'integer',
        'metadata'    => 'array',
    ];

    // Fréquences
    public const FREQ_ONE_TIME  = 'one_time';
    public const FREQ_WEEKLY    = 'weekly';
    public const FREQ_BIWEEKLY  = 'biweekly';
    public const FREQ_MONTHLY   = 'monthly';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function preferredProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_provider_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'organization_site_id');
    }

    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'service_zone_id');
    }

    public function postalCode(): BelongsTo
    {
        return $this->belongsTo(PostalCode::class, 'postal_code_id');
    }

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

    public function scopeForOrg($query, int $orgId)
    {
        return $query->where('organization_account_id', $orgId);
    }

    // Helpers
    public function fullAddress(): string
    {
        return "{$this->address}, {$this->postal_code} {$this->city}";
    }

    public function frequencyLabel(): string
    {
        return match ($this->cleaning_frequency) {
            self::FREQ_ONE_TIME => 'Ponctuel',
            self::FREQ_WEEKLY   => 'Hebdomadaire',
            self::FREQ_BIWEEKLY => 'Bi-mensuel',
            self::FREQ_MONTHLY  => 'Mensuel',
            default             => 'Non défini',
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
                $site->address = trim($site->name . ' ' . $site->postal_code . ' ' . $site->city);
            }

            if (blank($site->address)) {
                $site->address = 'Adresse non renseignée';
            }

            if (blank($site->address_line_1) && filled($site->address)) {
                $site->address_line_1 = $site->address;
            }
        });
    }

    public function organizationAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\OrganizationAccount::class, 'organization_account_id');
    }

    public function postalCodeReference(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\PostalCode::class, 'postal_code_id');
    }
}
