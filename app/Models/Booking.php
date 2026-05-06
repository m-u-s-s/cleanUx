<?php

namespace App\Models;

use App\Models\Concerns\HasBookingDisplayAccessors;
use App\Models\Concerns\HasRecurringSeries;
use App\Models\Concerns\ResetsNotificationTracking;
use App\Support\Domain\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Booking — entité canonique des réservations CleanUx.
 *
 * Ce modèle remplace l'ancien doublon `Bookings.php` (qui n'était utilisé nulle part).
 * Les traits HasRecurringSeries / HasBookingDisplayAccessors / ResetsNotificationTracking
 * apportent : gestion des séries récurrentes, accessors d'affichage unifiés FR/EN,
 * et reset auto du tracking de notification quand la date/heure/status change.
 *
 * Les noms FR (client_id, date, heure, adresse…) sont conservés pour rétrocompat
 * et sont synchronisés automatiquement avec leurs équivalents modernes via
 * syncLegacyAliases() au moment du save.
 */
class Booking extends Model
{
    use HasFactory;
    use HasRecurringSeries;
    use HasBookingDisplayAccessors;
    use ResetsNotificationTracking;

    protected $table = 'bookings';

    protected $fillable = [
        // Identité réservation
        'booking_reference',
        'recurring_booking_series_id',
        'recurring_series_id',
        'is_series_master',
        'series_position',
        'is_recurrent',
        'is_favorite_slot',

        // Acteurs
        'customer_user_id',
        'customer_organization_id',
        'organization_account_id',
        'organization_site_id',

        // Service / zone
        'service_catalog_id',
        'service_zone_id',
        'postal_code_id',

        // Provider
        'preferred_provider_user_id',
        'assigned_provider_organization_id',
        'assigned_provider_user_id',
        'provider_team_id',

        // Planification
        'scheduled_date',
        'scheduled_time',
        'booking_mode',
        'status',
        'priority',

        // Caractéristiques du lieu / mission
        'place_type',
        'frequency',
        'surface_m2',

        // Adresse
        'address',
        'city',
        'postal_code',
        'country',
        'address_components',
        'destination_lat',
        'destination_lng',

        // Contact
        'contact_name',
        'contact_phone',
        'contact_email',

        // Commentaires & notes
        'customer_comment',
        'internal_notes',
        'motif',

        // Pricing
        'estimated_price',
        'estimated_duration_minutes',
        'currency',

        // Données structurées
        'options',
        'options_prestation',
        'areas',
        'zones_specifiques',
        'materiel_specifique',
        'photos_reference',
        'photos_avant',
        'photos_apres',
        'terrain_checklist',

        // Snapshots
        'pricing_snapshot',
        'zone_snapshot',
        'matching_snapshot',

        // Workflow
        'created_by',
        'approved_by',
        'approved_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',

        // Timestamps mission (terrain)
        'mission_started_at',
        'mission_arrived_at',
        'mission_finished_at',
        'client_presence_confirmed_at',
        'asap_requested_at',
        'asap_deadline_at',
        'matched_at',

        // Timestamps paiement
        'payment_authorized_at',
        'payment_captured_at',
        'payment_cancelled_at',
        'payment_failed_at',

        // Notifications tracking
        'rappel_24h_envoye_at',
        'rappel_2h_envoye_at',
        'alerte_urgence_envoyee_at',

        // Drapeaux terrain
        'presence_animaux',
        'acces_parking',
        'materiel_fournit',

        // Legacy FR (synchronisés automatiquement)
        'client_id',
        'employe_id',
        'date',
        'heure',
        'adresse',
        'ville',
        'code_postal',
        'type_lieu',
        'surface',
        'frequence',
        'priorite',
        'telephone_client',
        'commentaire_client',
        'devis_estime',
        'duree_estimee',
    ];

    protected $casts = [
        // Dates & datetimes
        'scheduled_date'                => 'date',
        'scheduled_time'                => 'datetime:H:i',
        'date'                          => 'date',
        'approved_at'                   => 'datetime',
        'cancelled_at'                  => 'datetime',
        'mission_started_at'            => 'datetime',
        'mission_arrived_at'            => 'datetime',
        'mission_finished_at'           => 'datetime',
        'client_presence_confirmed_at'  => 'datetime',
        'asap_requested_at'             => 'datetime',
        'asap_deadline_at'              => 'datetime',
        'matched_at'                    => 'datetime',
        'payment_authorized_at'         => 'datetime',
        'payment_captured_at'           => 'datetime',
        'payment_cancelled_at'          => 'datetime',
        'payment_failed_at'             => 'datetime',
        'rappel_24h_envoye_at'          => 'datetime',
        'rappel_2h_envoye_at'           => 'datetime',
        'alerte_urgence_envoyee_at'     => 'datetime',

        // Booléens
        'presence_animaux'   => 'boolean',
        'acces_parking'      => 'boolean',
        'materiel_fournit'   => 'boolean',
        'is_recurrent'       => 'boolean',
        'is_series_master'   => 'boolean',
        'is_favorite_slot'   => 'boolean',

        // Décimaux
        'estimated_price'   => 'decimal:2',
        'devis_estime'      => 'decimal:2',
        'destination_lat'   => 'decimal:7',
        'destination_lng'   => 'decimal:7',

        // Entiers
        'estimated_duration_minutes' => 'integer',
        'duree_estimee'              => 'integer',
        'surface_m2'                 => 'integer',

        // JSON / arrays
        'options'             => 'array',
        'options_prestation'  => 'array',
        'areas'               => 'array',
        'zones_specifiques'   => 'array',
        'materiel_specifique' => 'array',
        'photos_reference'    => 'array',
        'photos_avant'        => 'array',
        'photos_apres'        => 'array',
        'terrain_checklist'   => 'array',
        'pricing_snapshot'    => 'array',
        'zone_snapshot'       => 'array',
        'matching_snapshot'   => 'array',
        'address_components'  => 'array',
    ];

    // ──────────────────────────────────────────────────────
    // Booted hooks (sync legacy aliases & notification reset)
    // ──────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (Booking $booking) {
            $booking->syncLegacyAliases();
        });
    }

    /**
     * Synchronise les paires legacy_fr ↔ modern_en pour qu'elles soient toujours
     * cohérentes en base, peu importe par quel champ on a écrit.
     */
    public function syncLegacyAliases(): void
    {
        $pairs = [
            ['client_id',           'customer_user_id'],
            ['employe_id',          'assigned_provider_user_id'],
            ['organization_account_id', 'customer_organization_id'],
            ['date',                'scheduled_date'],
            ['heure',               'scheduled_time'],
            ['adresse',             'address'],
            ['ville',               'city'],
            ['code_postal',         'postal_code'],
            ['type_lieu',           'place_type'],
            ['surface',             'surface_m2'],
            ['frequence',           'frequency'],
            ['priorite',            'priority'],
            ['commentaire_client',  'customer_comment'],
            ['telephone_client',    'contact_phone'],
            ['devis_estime',        'estimated_price'],
            ['duree_estimee',       'estimated_duration_minutes'],
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

    // ──────────────────────────────────────────────────────
    // Relations — acteurs
    // ──────────────────────────────────────────────────────

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

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function assignedProviderOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'assigned_provider_organization_id');
    }

    public function organizationSite(): BelongsTo
    {
        return $this->belongsTo(OrganizationSite::class, 'organization_site_id');
    }

    // ──────────────────────────────────────────────────────
    // Relations — service / zone / mission
    // ──────────────────────────────────────────────────────

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
        return $this->hasOne(Mission::class, 'booking_id');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class, 'booking_id');
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

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class, 'booking_id');
    }

    // ──────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────

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

    // ──────────────────────────────────────────────────────
    // Helpers de statut
    // ──────────────────────────────────────────────────────

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

    // ──────────────────────────────────────────────────────
    // Accessors d'affichage
    // ──────────────────────────────────────────────────────
    // Note: HasBookingDisplayAccessors fournit déjà des accessors.
    // Les méthodes ci-dessous existaient dans l'ancien Booking.php
    // et sont conservées pour rétrocompatibilité — si un getter de même
    // nom existe dans le trait, le trait gagne (plus à jour).

    public function getDisplayAddressAttribute(): string
    {
        return $this->address ?? $this->adresse ?? '';
    }

    public function getDisplayCityAttribute(): string
    {
        return $this->city ?? $this->ville ?? '';
    }

    public function getDisplayPostalCodeAttribute(): string
    {
        return $this->postal_code ?? $this->code_postal ?? '';
    }

    public function getDisplayDateAttribute(): mixed
    {
        return $this->scheduled_date ?? $this->date;
    }

    public function getDisplayTimeAttribute(): mixed
    {
        return $this->scheduled_time ?? $this->heure;
    }
}
