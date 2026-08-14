<?php

namespace App\Models;

use App\Models\Concerns\HasBookingDisplayAccessors;
use App\Models\Concerns\HasBookingPricing;
use App\Models\Concerns\HasLegacyBookingAliases;
use App\Models\Concerns\HasRecurringSeries;
use App\Models\Concerns\ResetsNotificationTracking;
use App\Support\Domain\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Booking — entité canonique des réservations Brio.
 *
 * Ce modèle remplace l'ancien doublon `Bookings.php` (qui n'était utilisé nulle part).
 * Les traits HasRecurringSeries / HasBookingDisplayAccessors / ResetsNotificationTracking
 * apportent : gestion des séries récurrentes, accessors d'affichage unifiés FR/EN,
 * et reset auto du tracking de notification quand la date/heure/status change.
 *
 * Les noms FR (client_id, date, heure, adresse…) sont conservés pour rétrocompat
 * et sont synchronisés automatiquement avec leurs équivalents modernes via
 * syncLegacyAliases() au moment du save.
 *
 * Accessors declared in HasBookingDisplayAccessors (Larastan does not infer
 * getXAttribute through traits at level 6):
 *
 * @property ?string $beneficiary_name
 * @property ?string $beneficiary_phone
 * @property ?string $beneficiary_note
 * @property ?int $client_place_id
 * @property bool $client_absent
 * @property ?string $client_absent_instructions
 * @property ?string $backup_contact_name
 * @property ?string $backup_contact_phone
 * @property ?Carbon $checkin_ping_sent_at
 * @property ?string $checkin_ping_answer
 * @property ?Carbon $checkin_ping_answered_at
 * @property-read string $service_display_name
 * @property string $total
 * @property ?string $trade_name
 * @property ?int $trade_id
 * @property ?int $postal_code_id
 * @property ?int $user_id
 * @property-read int $total_cents
 * @property-read ?string $adresse_complete
 * @property-read int|string $month
 * @property ?Carbon $feedback_demande_envoye_at
 * @property ?Carbon $scheduled_date
 * @property ?Carbon $scheduled_time
 * @property ?Carbon $date
 * @property ?Carbon $approved_at
 * @property ?Carbon $cancelled_at
 * @property ?Carbon $mission_started_at
 * @property ?Carbon $mission_arrived_at
 * @property ?Carbon $mission_finished_at
 * @property ?Carbon $client_presence_confirmed_at
 * @property ?Carbon $asap_requested_at
 * @property ?Carbon $asap_deadline_at
 * @property ?Carbon $matched_at
 * @property ?Carbon $payment_authorized_at
 * @property ?Carbon $payment_captured_at
 * @property ?Carbon $payment_cancelled_at
 * @property ?Carbon $payment_failed_at
 * @property ?Carbon $rappel_24h_envoye_at
 * @property ?Carbon $rappel_2h_envoye_at
 * @property ?Carbon $alerte_urgence_envoyee_at
 * @property bool $presence_animaux
 * @property bool $acces_parking
 * @property bool $materiel_fournit
 * @property bool $is_recurrent
 * @property bool $is_favorite_slot
 * @property string $estimated_price
 * @property string $devis_estime
 * @property string $destination_lat
 * @property string $destination_lng
 * @property int $estimated_duration_minutes
 * @property int $duree_estimee
 * @property int $surface_m2
 * @property array $options
 * @property array $options_prestation
 * @property array $areas
 * @property array $zones_specifiques
 * @property array $materiel_specifique
 * @property array $photos_reference
 * @property array $photos_avant
 * @property array $photos_apres
 * @property array $trade_form_answers
 * @property array $terrain_checklist
 * @property array $pricing_snapshot
 * @property array $zone_snapshot
 * @property array $matching_snapshot
 * @property array $address_components
 * @property array $metadata
 * @property bool $is_series_master
 * @property int $series_position
 * @property int $recurrence_interval
 * @property int $recurrence_count
 * @property ?Carbon $recurrence_until
 * @property array $recurrence_days
 */
class Booking extends Model
{
    use HasBookingDisplayAccessors;
    use HasBookingPricing;

    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    use HasLegacyBookingAliases;
    use HasRecurringSeries;
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
        'organization_contract_id',
        // Demande mère d'un groupement multi-sites. Sans être assignable en masse, la colonne
        // était rejetée en silence par create() : les filles naissaient orphelines.
        'parent_booking_id',

        // Service / zone
        'service_catalog_id',
        // Le métier, en clair. Le dispatch en fait un invariant : sans lui, il ne cherche personne
        // plutôt que de chercher n'importe qui.
        'trade_id',
        'service_zone_id',
        'postal_code_id',

        // Provider
        'preferred_provider_user_id',
        'provider_type_preference',
        'assigned_provider_organization_id',
        'assigned_provider_user_id',
        'provider_team_id',

        // Planification
        'scheduled_date',
        'scheduled_time',
        // L'HORODATAGE COMPLET du rendez-vous. La colonne existait et n'était jamais remplie :
        // absente de `$fillable`, toute écriture était silencieusement ignorée. Le moteur
        // d'annulation la lit pourtant EN PREMIER, et retombait donc sur `date` — une colonne
        // MySQL de type DATE, tronquée au jour. Les frais d'annulation se calculaient ainsi
        // contre minuit au lieu de l'heure réelle du rendez-vous.
        'scheduled_at',
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
        // L'acompte est DÉBITÉ, le solde seulement BLOQUÉ : deux natures, donc deux colonnes.
        'payment_plan',
        'deposit_payment_intent_id',
        'deposit_amount_cents',
        'deposit_captured_at',

        // Payout engine (Phase monetisation)
        'stripe_transfer_id',

        // Notifications tracking
        'rappel_24h_envoye_at',
        'rappel_2h_envoye_at',
        'alerte_urgence_envoyee_at',
        'feedback_demande_envoye_at',
        'remarque_terrain',

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
        'surface',        // M8 — virtual alias bridged to surface_range (see surface() attribute)
        'surface_range',
        'frequence',
        'priorite',
        'telephone_client',
        'commentaire_client',
        'devis_estime',
        'duree_estimee',

        // Timestamps explicites (autorisés pour fixtures de tests rétro-datées)
        'created_at',
        'updated_at',

        /*
         * LE BÉNÉFICIAIRE (E1) — le client paye, quelqu'un d'autre reçoit.
         *
         * Il SURVIT à la conversion du panier : un bénéficiaire qui ne franchirait pas la
         * confirmation ne servirait à personne, et le prestataire arriverait en demandant celui
         * qui a payé.
         */
        'beneficiary_name',
        'beneficiary_phone',
        'beneficiary_note',
        // Le lieu du carnet (E2) : c'est lui qui porte l'étage, le digicode et les préférences
        // que la fiche d'accès sur place (F5) révèle à l'arrivée.
        'client_place_id',

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

        // ÉCRITE PAR LE CODE, ÉCARTÉE PAR ELOQUENT. Trois chemins la renseignent —
        // `CreateBookingAction`, `BookingHub` et l'import en masse B2B — et son absence de cette
        // liste la faisait disparaître SANS ERREUR. Résultat : toutes les réservations naissaient
        // sans canal d'origine, et l'analyse par canal ne pouvait rien dire.
        'booking_channel',

        // Écrite par le code, écartée par Eloquent faute de figurer ici.
        'notes',

        // F14 / F15 — déclaration d'absence, contact de secours, et ping de mi-mission.
        'client_absent',
        'client_absent_instructions',
        'backup_contact_name',
        'backup_contact_phone',
    ];

    protected $casts = [
        // Dates & datetimes
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime:H:i',
        'scheduled_at' => 'datetime',
        'date' => 'date',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'mission_started_at' => 'datetime',
        'mission_arrived_at' => 'datetime',
        'mission_finished_at' => 'datetime',
        'client_presence_confirmed_at' => 'datetime',
        // F14 / F15 — le mode « je ne suis pas là » et le ping de mi-mission. Sans ces casts, une
        // date relue reste une chaîne, et le premier appelant qui la formate lève une erreur.
        'client_absent' => 'boolean',
        'checkin_ping_sent_at' => 'datetime',
        'checkin_ping_answered_at' => 'datetime',
        'asap_requested_at' => 'datetime',
        'asap_deadline_at' => 'datetime',
        'matched_at' => 'datetime',
        'payment_authorized_at' => 'datetime',
        'payment_captured_at' => 'datetime',
        'payment_cancelled_at' => 'datetime',
        'payment_failed_at' => 'datetime',
        'rappel_24h_envoye_at' => 'datetime',
        'rappel_2h_envoye_at' => 'datetime',
        'alerte_urgence_envoyee_at' => 'datetime',
        'feedback_demande_envoye_at' => 'datetime',

        // Booléens
        'presence_animaux' => 'boolean',
        'acces_parking' => 'boolean',
        'materiel_fournit' => 'boolean',
        'is_recurrent' => 'boolean',
        'is_favorite_slot' => 'boolean',

        // Décimaux
        'estimated_price' => 'decimal:2',
        'devis_estime' => 'decimal:2',
        'destination_lat' => 'decimal:7',
        'destination_lng' => 'decimal:7',

        // Entiers
        'estimated_duration_minutes' => 'integer',
        'duree_estimee' => 'integer',
        'surface_m2' => 'integer',

        // JSON / arrays
        'options' => 'array',
        'options_prestation' => 'array',
        'areas' => 'array',
        'zones_specifiques' => 'array',
        'materiel_specifique' => 'array',
        'photos_reference' => 'array',
        'photos_avant' => 'array',
        'photos_apres' => 'array',
        'trade_form_answers' => 'array',
        'terrain_checklist' => 'array',
        'pricing_snapshot' => 'array',
        'zone_snapshot' => 'array',
        'matching_snapshot' => 'array',
        'address_components' => 'array',
        'metadata' => 'array',

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

        static::saving(function (Booking $booking) {
            if (
                blank($booking->series_status)
                && filled($booking->recurring_series_id)
            ) {
                $booking->series_status = 'active';
            }
        });
    }

    // syncLegacyAliases() vit dans HasLegacyBookingAliases.

    /**
     * M8 — backward-compatible bridge for the renamed column. The DB column is now
     * `surface_range`; legacy code/views/forms still read & write `$booking->surface`, which this
     * virtual attribute maps to surface_range transparently.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function surface(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $attributes['surface_range'] ?? null,
            set: fn ($value) => ['surface_range' => $value],
        );
    }

    // ──────────────────────────────────────────────────────
    // Relations — acteurs
    // ──────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_provider_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function providerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_provider_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function customerOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'customer_organization_id');
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function assignedProviderOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'assigned_provider_organization_id');
    }

    /** @return BelongsTo<OrganizationContract, $this> */
    public function organizationContract(): BelongsTo
    {
        return $this->belongsTo(OrganizationContract::class, 'organization_contract_id');
    }

    /** @return BelongsTo<OrganizationSite, $this> */
    public function organizationSite(): BelongsTo
    {
        return $this->belongsTo(OrganizationSite::class, 'organization_site_id');
    }

    /**
     * Le lieu du carnet client (E2) — le pendant particulier de `organizationSite`.
     *
     * C'est lui qui porte l'étage, le digicode et les préférences que la fiche d'accès sur place
     * révèle à l'arrivée. Sans cette relation, le carnet ne serait qu'un formulaire d'adresse.
     *
     * @return BelongsTo<ClientPlace, $this>
     */
    public function clientPlace(): BelongsTo
    {
        return $this->belongsTo(ClientPlace::class, 'client_place_id');
    }

    /**
     * DEMANDE MÈRE / RÉSERVATIONS FILLES — UN LIEN QUI EXISTAIT SANS ÊTRE LISIBLE.
     *
     * `bookings.parent_booking_id` figure dans la migration initiale (FK nullable, `nullOnDelete`)
     * mais aucune relation ne l'exposait, aucun code ne l'écrivait, et la colonne ne comptait zéro
     * ligne. Elle sert désormais aux demandes couvrant plusieurs sites : une mère porte l'intention
     * commune, chaque site reçoit sa fille.
     *
     * À ne pas confondre avec `recurring_series_id`, qui gouverne la répétition dans le temps.
     *
     * @return BelongsTo<self, $this>
     */
    public function parentBooking(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_booking_id');
    }

    /** @return HasMany<self, $this> */
    public function childBookings(): HasMany
    {
        return $this->hasMany(self::class, 'parent_booking_id');
    }

    // ──────────────────────────────────────────────────────
    // Relations — service / zone / mission
    // ──────────────────────────────────────────────────────

    /** @return BelongsTo<ServiceCatalog, $this> */
    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }

    /**
     * LE MÉTIER DE CETTE RÉSERVATION — colonne propre, plus une déduction.
     *
     * `bookings.trade_id` est écrit par le moteur de commande, qui SAIT quel métier a été choisi.
     * Auparavant le métier ne se lisait qu'en traversant `service_catalog_id` → `trade_id` : une
     * chaîne qui casse dès qu'une réservation n'a pas de service au catalogue, ce qui est le cas de
     * toutes celles du parcours de commande. Le dispatch retombait alors sur « pas de métier connu,
     * on ne filtre pas » — la porte par laquelle un peintre pouvait recevoir du babysitting.
     *
     * @return BelongsTo<Trade, $this>
     */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /**
     * Le métier déduit du service au catalogue — le REPLI, pour les réservations antérieures à la
     * colonne. Conservé exprès : les archives n'ont pas de `trade_id` et doivent rester lisibles.
     *
     * @return HasOneThrough<Trade, ServiceCatalog, $this>
     */
    public function tradeViaCatalog(): HasOneThrough
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

    /**
     * Le métier de cette réservation, colonne d'abord, catalogue ensuite.
     *
     * Un seul endroit décide de cet ordre. Le dupliquer dans chaque appelant ferait qu'un chemin
     * lirait la colonne et l'autre le catalogue — et deux chemins trouveraient deux métiers
     * différents pour la même réservation.
     */
    public function resolveTradeId(): ?int
    {
        if ($this->trade_id) {
            return (int) $this->trade_id;
        }

        $viaCatalog = $this->serviceCatalog?->trade_id;

        return $viaCatalog ? (int) $viaCatalog : null;
    }

    /** Le métier résolu, quel que soit le chemin. */
    public function resolveTrade(): ?Trade
    {
        $id = $this->resolveTradeId();

        return $id ? Trade::query()->whereKey($id)->first() : null;
    }

    /** @return BelongsTo<ServiceZone, $this> */
    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    /** @return BelongsTo<PostalCode, $this> */
    public function postalCode(): BelongsTo
    {
        return $this->belongsTo(PostalCode::class);
    }

    /** @return HasMany<Mission, $this> */
    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    /**
     * QUI INTERVIENT RÉELLEMENT — la seule réponse qui fasse autorité.
     *
     * DEUX CHAMPS RÉPONDAIENT À LA MÊME QUESTION. `bookings.employe_id` est le prestataire de la
     * COMMANDE : `MissionFromRendezVousSyncService` le recopie vers la mission à la création, si
     * bien que les deux disent la même chose sur un parcours nominal. Ils ne DIVERGENT qu'à la
     * première réassignation — et pour toute mission qu'une société confie à l'un de ses salariés.
     *
     * C'est ce qui a rendu le défaut invisible : il n'apparaît qu'après un changement
     * d'intervenant, moment où plus personne ne relit le code. Dix-huit lecteurs répondaient alors
     * faux — l'ancien prestataire gardait l'accès au client, touchait le pourboire, recevait les
     * étoiles et l'événement d'agenda, pendant que celui qui avait travaillé n'avait rien.
     *
     * LA MISSION FAIT AUTORITÉ, la réservation reste en repli : avant qu'une mission existe, elle
     * porte la seule information disponible, et les parcours qui n'ont jamais divergé gardent leur
     * comportement.
     *
     * Ne PAS lire `employe_id` directement pour répondre à « qui intervient » — c'est le sens de
     * `ReservationIntervenantTest`, qui refuse toute réapparition.
     */
    public function intervenantId(): ?int
    {
        // Quand l'appelant a déjà chargé `missions`, on ne repart pas en base : ce résolveur est
        // lu dans des boucles d'affichage (planning, agenda), où une requête par ligne coûterait
        // plus cher que le défaut qu'il corrige.
        $depuisLaMission = $this->relationLoaded('missions')
            ? $this->missions->sortByDesc('id')->first()?->lead_provider_user_id
            : $this->missions()->latest('id')->value('lead_provider_user_id');

        $id = $depuisLaMission
            ?? $this->employe_id
            ?? $this->assigned_provider_user_id
            ?? $this->provider_user_id
            ?? $this->assigned_employee_id
            ?? null;

        return $id ? (int) $id : null;
    }

    /** L'intervenant lui-même, quand on a besoin de l'utilisateur et pas de son identifiant. */
    public function intervenant(): ?User
    {
        $id = $this->intervenantId();

        return $id ? User::find($id) : null;
    }

    /**
     * LA MÊME RÈGLE, EN SQL — pour les écrans qui filtrent au lieu de parcourir.
     *
     * Le planning et l'agenda d'administration ne lisent pas une réservation à la fois : ils
     * filtrent une semaine entière. `intervenantId()` leur est inaccessible, et recopier la règle
     * dans un `where` est exactement ce qui a produit le défaut d'origine.
     *
     * L'ordre est celui du résolveur, et il compte : la mission d'abord, la réservation seulement
     * quand AUCUNE mission ne désigne personne. `ReservationIntervenantTest` compare les deux
     * formulations sur un jeu mélangé — si elles se mettaient à diverger, le test le dirait.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeIntervenantEst(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->whereHas('missions', fn ($m) => $m->where('lead_provider_user_id', $userId))
                ->orWhere(function (Builder $repli) use ($userId) {
                    $repli->whereDoesntHave('missions', fn ($m) => $m->whereNotNull('lead_provider_user_id'))
                        ->where(fn (Builder $colonnes) => $colonnes
                            ->where('employe_id', $userId)
                            ->orWhere('assigned_provider_user_id', $userId));
                });
        });
    }

    /**
     * Les réservations que PERSONNE ne prend en charge — celles qu'une administration doit voir
     * pour les attribuer.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeSansIntervenant(Builder $query): Builder
    {
        return $query->whereDoesntHave('missions', fn ($m) => $m->whereNotNull('lead_provider_user_id'))
            ->whereNull('employe_id')
            ->whereNull('assigned_provider_user_id');
    }

    /** @return HasMany<ComplaintCase, $this> */
    public function complaintCases(): HasMany
    {
        return $this->hasMany(ComplaintCase::class, 'booking_id');
    }

    /** @return HasOne<Feedback, $this> */
    public function mission(): HasOne
    {
        return $this->hasOne(Mission::class, 'booking_id');
    }

    /**
     * La mission de cette réservation.
     *
     * Elle interrogeait DEUX colonnes — `booking_id` et `rendez_vous_id` — parce que le chemin de
     * création décidait laquelle était remplie : deux chemins pouvaient donc créer chacun leur
     * mission sans jamais se voir. Les deux colonnes sont fusionnées ; la méthode reste, parce que
     * c'est le point de résolution unique que les appelants connaissent, et qu'un `->mission`
     * direct rendrait la relation avant que la mission n'existe.
     */
    public function resolveMission(): ?Mission
    {
        return Mission::query()
            ->where('booking_id', $this->id)
            ->orderBy('id')
            ->first();
    }

    /** @return HasOne<Feedback, $this> */
    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class, 'booking_id');
    }

    /** @return HasMany<Feedback, $this> */
    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    /** @return HasOne<BookingApproval, $this> */
    public function latestFeedback(): HasOne
    {
        return $this->hasOne(Feedback::class)->latestOfMany();
    }

    /** @return HasMany<BookingApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(BookingApproval::class);
    }

    /** @return HasMany<BookingAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(BookingAttachment::class);
    }

    /** @return HasOne<Conversation, $this> */
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

        $like = '%'.$term.'%';

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

        $like = '%'.$term.'%';

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
                ->orWhereHas('employe', fn (Builder $q) => $q->where('name', 'like', $like))
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
    // Helpers provider preference
    // ──────────────────────────────────────────────────────

    public function prefersProviderType(string $type): bool
    {
        return ($this->provider_type_preference ?? 'any') === $type;
    }

    // ──────────────────────────────────────────────────────
    // Helpers de statut
    // ──────────────────────────────────────────────────────
    //
    // BookingStatus est une CLASSE à constantes (pas un enum), avec des
    // valeurs en français (EN_ATTENTE, CONFIRME, ANNULE, TERMINE...).
    // Les variantes en anglais sont acceptées pour rétrocompat avec
    // le code récent (assistant LLM, bookings v3, tests, etc.).

    /**
     * Les statuts qui valent « en attente », les deux langues confondues.
     *
     * Extrait de `isPending()` pour que les REQUÊTES puissent compter la même chose que les
     * OBJETS. Une liste recopiée dans un `whereIn` divergerait à la première valeur ajoutée ici,
     * et un compteur d'accueil faux ne se remarque pas : il a l'air d'un chiffre.
     *
     * @var list<string>
     */
    public const PENDING_STATUSES = [
        BookingStatus::EN_ATTENTE,
        'pending',
        'pending_approval',
        'pending_assignment',
        'draft',
    ];

    public function isPending(): bool
    {
        return in_array($this->status, self::PENDING_STATUSES, true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', self::PENDING_STATUSES);
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

    // Display accessors (getDisplayAddressAttribute etc.) moved to HasBookingDisplayAccessors.
    // Pricing accessors (getFinalPriceAttribute etc.) moved to HasBookingPricing.

    /** @return HasOne<FinanceQuote, $this> */
    public function financeQuote(): HasOne
    {
        return $this->hasOne(FinanceQuote::class, 'booking_id');
    }

    /** @return HasOne<FinanceInvoice, $this> */
    public function financeInvoice(): HasOne
    {
        return $this->hasOne(FinanceInvoice::class, 'rendez_vous_id');
    }

    /**
     * La mission d'exploitation — la plus récente.
     *
     * Elle essayait `rendez_vous_id` PUIS `booking_id`, en interrogeant le schéma à chaque appel
     * pour savoir si la colonne existait. Une seule colonne subsiste, et la question ne se pose
     * plus.
     */
    public function operationalMission(): ?Mission
    {
        return Mission::query()
            ->where('booking_id', $this->id)
            ->latest('id')
            ->first();
    }
}
