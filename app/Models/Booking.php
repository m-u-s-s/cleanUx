<?php

namespace App\Models;

use App\Models\Mission;
use App\Models\Concerns\HasBookingDisplayAccessors;
use App\Models\Concerns\HasRecurringSeries;
use App\Models\Concerns\ResetsNotificationTracking;
use App\Support\Domain\BookingStatus;
use Illuminate\Support\Facades\Schema;
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
        'organization_account_id',

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
        // Phase F1 — réponses dynamiques au schema de formulaire du Trade
        'trade_form_answers',

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
        'payment_refunded_at',

        // Stripe Connect & paiement (Phase Stripe v2)
        'stripe_payment_intent_id',
        'payment_status',
        'payment_amount_cents',
        'provider_amount_cents',
        'platform_fee_cents',

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

        // Timestamps explicites (autorisés pour fixtures de tests rétro-datées)
        'created_at',
        'updated_at',

        // Métadonnées libres
        'metadata',

        // recurrence
        'recurrence_rule',
        'recurring_series_id',
        'recurrence_frequency',
        'recurrence_interval',
        'recurrence_until',
        'recurrence_count',
        'recurrence_days',
        'is_series_master',
        'series_position',
        'series_status',
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
        'trade_form_answers'  => 'array',
        'terrain_checklist'   => 'array',
        'pricing_snapshot'    => 'array',
        'zone_snapshot'       => 'array',
        'matching_snapshot'   => 'array',
        'address_components'  => 'array',
        'metadata'            => 'array',

        // recurrence
        'is_series_master' => 'boolean',
        'series_position' => 'integer',
        'recurrence_interval' => 'integer',
        'recurrence_count' => 'integer',
        'recurrence_until' => 'date',
        'recurrence_days' => 'array',
    ];

    // ──────────────────────────────────────────────────────
    // Booted hooks (sync legacy aliases & notification reset)
    // ──────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (Booking $booking) {
            $booking->syncLegacyAliases();
        });

        static::saved(function (Booking $booking) {
            $booking->mirrorIntoLegacyRendezVousTable();
        });

        static::saving(function (Booking $booking) {
            if (
                blank($booking->series_status)
                && filled($booking->recurring_series_id)
            ) {
                $booking->series_status = 'active';
            }
        });
    }

    /**
     * Synchronise les paires legacy_fr ↔ modern_en pour qu'elles soient toujours
     * cohérentes en base, peu importe par quel champ on a écrit.
     */
    /**
     * Réplique l'enregistrement dans la table héritée `rendez_vous`
     * (utilisée par d'anciennes assertions de tests et certains rapports
     * non encore migrés). L'opération est silencieuse si la table n'existe pas.
     */
    public function mirrorIntoLegacyRendezVousTable(): void
    {
        if (! Schema::hasTable('rendez_vous')) {
            return;
        }

        $cachedColumns = Schema::getColumnListing('rendez_vous');

        $candidate = [
            'id'                 => $this->id,
            'booking_reference'  => $this->booking_reference,
            'client_id'          => $this->client_id,
            'employe_id'         => $this->employe_id,
            'user_id'            => $this->client_id,
            'service_catalog_id' => $this->service_catalog_id,
            'service_zone_id'    => $this->service_zone_id,
            'postal_code_id'     => $this->postal_code_id,
            'status'             => $this->status,
            'date'               => $this->date,
            'heure'              => $this->heure,
            'scheduled_at'       => $this->scheduled_at,
            'adresse'            => $this->adresse,
            'address'            => $this->adresse,
            'ville'              => $this->ville,
            'city'               => $this->ville,
            'code_postal'        => $this->code_postal,
            'postal_code'        => $this->code_postal,
            'zone_snapshot'      => is_array($this->zone_snapshot) ? json_encode($this->zone_snapshot) : $this->zone_snapshot,
            'pricing_snapshot'   => is_array($this->pricing_snapshot) ? json_encode($this->pricing_snapshot) : $this->pricing_snapshot,
            'estimated_price'    => $this->estimated_price ?? $this->devis_estime,
            'final_price'        => $this->final_price,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];

        $payload = collect($candidate)
            ->filter(fn ($value, $key) => in_array($key, $cachedColumns, true))
            ->all();

        if (empty($payload['id'])) {
            return;
        }

        try {
            \Illuminate\Support\Facades\DB::table('rendez_vous')->updateOrInsert(
                ['id' => $payload['id']],
                $payload,
            );
        } catch (\Throwable $e) {
            // ignore : la table peut avoir des FK strictes différentes selon l'environnement
        }
    }

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

    /**
     * Métier requis pour cette réservation, résolu via le ServiceCatalog.
     * Null si le service n'est rattaché à aucun trade (back-compat phase de transition).
     */
    public function trade(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            Trade::class,
            ServiceCatalog::class,
            'id',                  // PK de service_catalogs
            'id',                  // PK de trades
            'service_catalog_id',  // FK locale (bookings)
            'trade_id'             // FK intermédiaire (service_catalogs → trades)
        );
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

    public function scopeWhereServiceMatches(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%' . $term . '%';

        return $query->where(function (Builder $inner) use ($like) {
            $inner
                ->whereHas('serviceCatalog', function (Builder $q) use ($like) {
                    $q->where('service_type', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhere('name', 'like', $like);
                });
        });
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
                ->orWhereHas('client', fn(Builder $q) => $q->where('name', 'like', $like))
                ->orWhereHas('employe', fn(Builder $q) => $q->where('name', 'like', $like))
                ->orWhereHas('serviceCatalog', function (Builder $q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhere('service_type', 'like', $like);
                })
                ->orWhereHas('postalCode', function (Builder $q) use ($like) {
                    $q->where('code', 'like', $like)
                        ->orWhere('city_name', 'like', $like);
                });
        });
    }

    // ──────────────────────────────────────────────────────
    // Helpers de statut
    // ──────────────────────────────────────────────────────
    //
    // BookingStatus est une CLASSE à constantes (pas un enum), avec des
    // valeurs en français (EN_ATTENTE, CONFIRME, ANNULE, TERMINE...).
    // Les variantes en anglais sont acceptées pour rétrocompat avec
    // le code récent (assistant LLM, bookings v3, tests, etc.).

    public function isPending(): bool
    {
        return in_array($this->status, [
            BookingStatus::EN_ATTENTE,
            'pending',
            'pending_approval',
            'pending_assignment',
            'draft',
        ], true);
    }

    public function isConfirmed(): bool
    {
        return in_array($this->status, [
            BookingStatus::CONFIRME,
            'confirmed',
        ], true);
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, [
            BookingStatus::EN_ROUTE,
            BookingStatus::SUR_PLACE,
            'in_progress',
            'on_route',
            'on_site',
        ], true);
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, [
            BookingStatus::ANNULE,
            BookingStatus::REFUSE,
            'cancelled',
            'refused',
        ], true);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [
            BookingStatus::TERMINE,
            'completed',
            'done',
        ], true);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, BookingStatus::final(), true)
            || $this->isCompleted()
            || $this->isCancelled();
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

    public function financeQuote(): HasOne
    {
        return $this->hasOne(FinanceQuote::class, 'booking_id');
    }

    public function financeInvoice(): HasOne
    {
        return $this->hasOne(FinanceInvoice::class, 'rendez_vous_id');
    }

    public function operationalMission(): ?Mission
    {
        $query = Mission::query();

        if (Schema::hasColumn('missions', 'rendez_vous_id')) {
            return $query
                ->where('rendez_vous_id', $this->id)
                ->latest('id')
                ->first();
        }

        if (Schema::hasColumn('missions', 'booking_id')) {
            return $query
                ->where('booking_id', $this->id)
                ->latest('id')
                ->first();
        }

        return null;
    }
}
