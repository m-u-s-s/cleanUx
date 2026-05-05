<?php

namespace App\Models;

use App\Models\Concerns\HasBookingDisplayAccessors;
use App\Models\Concerns\HasRecurringSeries;
use App\Models\Concerns\ResetsNotificationTracking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;
    use HasRecurringSeries;
    use HasBookingDisplayAccessors;
    use ResetsNotificationTracking;

    protected $table = 'bookings';

    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'date',
        'date' => 'date',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'mission_started_at' => 'datetime',
        'mission_arrived_at' => 'datetime',
        'mission_finished_at' => 'datetime',
        'client_presence_confirmed_at' => 'datetime',
        'asap_requested_at' => 'datetime',
        'asap_deadline_at' => 'datetime',
        'matched_at' => 'datetime',
        'payment_authorized_at' => 'datetime',
        'payment_captured_at' => 'datetime',
        'payment_cancelled_at' => 'datetime',
        'payment_failed_at' => 'datetime',
        'presence_animaux' => 'boolean',
        'acces_parking' => 'boolean',
        'materiel_fournit' => 'boolean',
        'is_recurrent' => 'boolean',
        'is_series_master' => 'boolean',
        'is_favorite_slot' => 'boolean',
        'photos_reference' => 'array',
        'photos_avant' => 'array',
        'photos_apres' => 'array',
        'terrain_checklist' => 'array',
        'options' => 'array',
        'options_prestation' => 'array',
        'areas' => 'array',
        'zones_specifiques' => 'array',
        'materiel_specifique' => 'array',
        'zone_snapshot' => 'array',
        'pricing_snapshot' => 'array',
        'matching_snapshot' => 'array',
        'address_components' => 'array',
        'destination_lat' => 'decimal:7',
        'destination_lng' => 'decimal:7',
    ];

    protected static function booted(): void
    {
        static::saving(function (Booking $booking) {
            $booking->syncLegacyAliases();
        });
    }

    public function syncLegacyAliases(): void
    {
        $pairs = [
            ['client_id', 'customer_user_id'],
            ['employe_id', 'assigned_provider_user_id'],
            ['organization_account_id', 'customer_organization_id'],
            ['date', 'scheduled_date'],
            ['heure', 'scheduled_time'],
            ['adresse', 'address'],
            ['ville', 'city'],
            ['code_postal', 'postal_code'],
            ['type_lieu', 'place_type'],
            ['surface', 'surface_m2'],
            ['frequence', 'frequency'],
            ['priorite', 'priority'],
            ['commentaire_client', 'customer_comment'],
            ['telephone_client', 'contact_phone'],
            ['devis_estime', 'estimated_price'],
            ['duree_estimee', 'estimated_duration_minutes'],
        ];

        foreach ($pairs as [$legacy, $modern]) {
            if (blank($this->{$legacy}) && filled($this->{$modern})) {
                $this->{$legacy} = $this->{$modern};
            }

            if (blank($this->{$modern}) && filled($this->{$legacy})) {
                $this->{$modern} = $this->{$legacy};
            }
        }
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function assignedProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_provider_user_id');
    }

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function customerOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'customer_organization_id');
    }

    public function organizationSite(): BelongsTo
    {
        return $this->belongsTo(OrganizationSite::class, 'organization_site_id');
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

    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class, 'booking_id');
    }

    public function mission(): HasOne
    {
        return $this->hasOne(Mission::class, 'booking_id');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class, 'booking_id');
    }

    public function scopeSearchStructured(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%' . $term . '%';

        return $query->where(function (Builder $searchQuery) use ($like) {
            $searchQuery
                ->where('booking_reference', 'like', $like)
                ->orWhere('adresse', 'like', $like)
                ->orWhere('address', 'like', $like)
                ->orWhere('ville', 'like', $like)
                ->orWhere('city', 'like', $like)
                ->orWhere('telephone_client', 'like', $like)
                ->orWhere('contact_phone', 'like', $like)
                ->orWhere('motif', 'like', $like)
                ->orWhere('code_postal', 'like', $like)
                ->orWhere('postal_code', 'like', $like)
                ->orWhereHas('client', fn (Builder $q) => $q->where('name', 'like', $like))
                ->orWhereHas('employe', fn (Builder $q) => $q->where('name', 'like', $like));
        });
    }
}
