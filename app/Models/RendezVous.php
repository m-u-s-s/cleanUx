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

class RendezVous extends Model
{
    use HasFactory;
    use HasRecurringSeries;
    use HasBookingDisplayAccessors;
    use ResetsNotificationTracking;

    protected $table = 'rendez_vous';

    protected $fillable = [
        'client_id',
        'employe_id',
        'organization_account_id',
        'organization_site_id',
        'service_catalog_id',
        'service_zone_id',
        'postal_code_id',
        'booking_channel',
        'booking_reference',
        'zone_snapshot',
        'pricing_snapshot',
        'date',
        'heure',
        'duree',
        'motif',
        'status',
        'adresse',
        'ville',
        'code_postal',
        'type_lieu',
        'surface',
        'frequence',
        'is_recurrent',
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
        'is_favorite_slot',
        'commentaire_client',
        'telephone_client',
        'presence_animaux',
        'acces_parking',
        'materiel_fournit',
        'priorite',
        'photos_reference',
        'photos_avant',
        'terrain_checklist',
        'remarque_terrain',
        'incident_terrain',
        'client_presence_confirmed_at',
        'client_signature_path',
        'options_prestation',
        'zones_specifiques',
        'materiel_specifique',
        'commentaire_fin_mission',
        'duree_reelle',
        'photos_apres',
        'mission_started_at',
        'mission_finished_at',
        'rappel_24h_envoye_at',
        'rappel_2h_envoye_at',
        'feedback_demande_envoye_at',
        'alerte_urgence_envoyee_at',
        'duree_estimee',
        'devis_estime',
        'booking_mode',
        'asap_requested_at',
        'asap_deadline_at',
        'matched_at',
        'matching_snapshot',
        'payment_status',
        'stripe_payment_intent_id',
        'payment_authorized_at',
        'payment_captured_at',
        'payment_cancelled_at',
        'google_place_id',
        'destination_lat',
        'destination_lng',
        'address_components',
        'stripe_payment_intent_id',
        'stripe_connect_account_id',
        'payment_amount_cents',
        'platform_fee_cents',
        'provider_amount_cents',
        'payment_status',
        'payment_authorized_at',
        'payment_captured_at',
        'payment_failed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'duree' => 'integer',
        'duree_estimee' => 'integer',
        'duree_reelle' => 'integer',
        'devis_estime' => 'decimal:2',
        'presence_animaux' => 'boolean',
        'acces_parking' => 'boolean',
        'materiel_fournit' => 'boolean',
        'is_recurrent' => 'boolean',
        'recurrence_interval' => 'integer',
        'recurrence_until' => 'date',
        'recurrence_count' => 'integer',
        'recurrence_days' => 'array',
        'is_series_master' => 'boolean',
        'series_position' => 'integer',
        'is_favorite_slot' => 'boolean',
        'photos_reference' => 'array',
        'photos_avant' => 'array',
        'terrain_checklist' => 'array',
        'options_prestation' => 'array',
        'zones_specifiques' => 'array',
        'materiel_specifique' => 'array',
        'photos_apres' => 'array',
        'zone_snapshot' => 'array',
        'pricing_snapshot' => 'array',
        'mission_started_at' => 'datetime',
        'mission_arrived_at' => 'datetime',
        'client_presence_confirmed_at' => 'datetime',
        'mission_finished_at' => 'datetime',
        'rappel_24h_envoye_at' => 'datetime',
        'rappel_2h_envoye_at' => 'datetime',
        'feedback_demande_envoye_at' => 'datetime',
        'alerte_urgence_envoyee_at' => 'datetime',
        'asap_requested_at' => 'datetime',
        'asap_deadline_at' => 'datetime',
        'matched_at' => 'datetime',
        'matching_snapshot' => 'array',
        'payment_authorized_at' => 'datetime',
        'payment_captured_at' => 'datetime',
        'payment_cancelled_at' => 'datetime',
        'destination_lat' => 'decimal:7',
        'destination_lng' => 'decimal:7',
        'address_components' => 'array',
        'payment_authorized_at' => 'datetime',
        'payment_captured_at' => 'datetime',
        'payment_failed_at' => 'datetime',
        'payment_amount_cents' => 'integer',
        'platform_fee_cents' => 'integer',
        'provider_amount_cents' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
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
        return $this->hasOne(Feedback::class, 'rendez_vous_id');
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
                ->orWhere('ville', 'like', $like)
                ->orWhere('telephone_client', 'like', $like)
                ->orWhere('motif', 'like', $like)
                ->orWhere('code_postal', 'like', $like)
                ->orWhereHas('client', fn(Builder $clientQuery) => $clientQuery->where('name', 'like', $like))
                ->orWhereHas('employe', fn(Builder $employeeQuery) => $employeeQuery->where('name', 'like', $like))
                ->orWhereHas('serviceCatalog', function (Builder $serviceQuery) use ($like) {
                    $serviceQuery
                        ->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhere('slug', 'like', $like);
                })
                ->orWhereHas('serviceZone', function (Builder $zoneQuery) use ($like) {
                    $zoneQuery
                        ->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                })
                ->orWhereHas('postalCode', function (Builder $postalCodeQuery) use ($like) {
                    $postalCodeQuery
                        ->where('code', 'like', $like)
                        ->orWhere('city_name', 'like', $like);
                })
                ->orWhereHas('organizationSite', function (Builder $siteQuery) use ($like) {
                    $siteQuery
                        ->where('name', 'like', $like)
                        ->orWhere('site_code', 'like', $like)
                        ->orWhere('city', 'like', $like);
                })
                ->orWhereHas('organizationAccount', function (Builder $organizationQuery) use ($like) {
                    $organizationQuery
                        ->where('name', 'like', $like)
                        ->orWhere('vat_number', 'like', $like);
                });
        });
    }

    public function scopeWhereServiceMatches(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%' . $term . '%';

        return $query->whereHas('serviceCatalog', function (Builder $catalogQuery) use ($like) {
            $catalogQuery
                ->where('name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('slug', 'like', $like);
        });
    }

    public function financeQuote(): HasOne
    {
        return $this->hasOne(FinanceQuote::class, 'rendez_vous_id');
    }

    public function financeInvoice(): HasOne
    {
        return $this->hasOne(FinanceInvoice::class, 'rendez_vous_id');
    }

    public function mission(): HasOne
    {
        return $this->hasOne(Mission::class, 'rendez_vous_id');
    }

    public function conversation()
    {
        return $this->hasOne(\App\Models\Conversation::class, 'rendez_vous_id');
    }

    public function enterpriseApproval(): HasOne
    {
        return $this->hasOne(EnterpriseBookingApproval::class, 'rendez_vous_id');
    }
}
