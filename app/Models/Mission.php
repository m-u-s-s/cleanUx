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
 * @property ?string $start_lat
 * @property ?string $start_lng
 * @property ?string $end_lat
 * @property ?string $end_lng
 * @property ?string $destination_lat
 * @property ?string $destination_lng
 * @property ?Carbon $client_final_validated_at
 * @property ?array $quality_summary
 * @property ?Carbon $sla_response_due_at
 * @property ?Carbon $sla_resolution_due_at
 */
class Mission extends Model
{
    /** @use HasFactory<MissionFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'organization_account_id',
        'provider_organization_id',
        'provider_team_id',
        'field_team_id',
        'provider_agency_id',
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
        // Ce que valait la position au moment de clôturer, et ce que le contrôle en a conclu.
        'end_accuracy_m',
        'end_distance_m',
        'end_geo_verdict',
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
        'estimated_duration_minutes',
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

        // ÉCRITES PAR LE CODE, ÉCARTÉES PAR ELOQUENT. Ces colonnes existent en base et des
        // appels d'écriture les renseignent, mais leur absence de cette liste les faisait
        // disparaître SANS ERREUR — Eloquent écarte en silence ce qu'il ne peut pas assigner.
        // Le rapport de mission est généré à la clôture, puis son chemin était écarté sans un mot :
        // le fichier existait sur le disque et la mission ne savait plus où.
        'report_path',

        // LES TROIS LIENS DE L'EXÉCUTION B2B. Le générateur d'ordres de travail les écrivait déjà
        // alors qu'aucune des trois colonnes n'existait : la traçabilité complète d'un chantier
        // d'entreprise partait en silence. Les colonnes sont créées, ces clés les atteignent.
        'enterprise_work_order_id',
        'mission_batch_id',
        'mission_task_segment_id',
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
        'end_accuracy_m' => 'float',
        'end_distance_m' => 'integer',
        'destination_lat' => 'decimal:7',
        'destination_lng' => 'decimal:7',
        'client_final_validated_at' => 'datetime',
        'quality_summary' => 'array',
        'sla_response_due_at' => 'datetime',
        'sla_resolution_due_at' => 'datetime',
    ];

    /**
     * LA RÉSERVATION DE CETTE MISSION — une clé, une relation, une réponse.
     *
     * Il y en avait TROIS. `missions` portait deux colonnes vers la même table `bookings` selon le
     * chemin de création, et `booking()` choisissait la sienne À L'EXÉCUTION — ce que le chargement
     * anticipé de Laravel ne sait pas faire : il résout la relation sur une instance vierge, où
     * l'attribut est vide, et retombait donc toujours du même côté. D'où une deuxième relation pour
     * contourner, puis une troisième, et des appelants qui combinaient les deux à la main.
     *
     * Un appelant sur trois se trompait : la modale d'offre s'ouvrait sur des tirets, le dispatch
     * cherchait des réservations qu'il ne trouvait pas.
     *
     * La colonne survivante est `booking_id`, et le schéma l'avait déjà tranché : elle porte une
     * contrainte de clé étrangère vers `bookings`, `rendez_vous_id` n'en a jamais eu.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
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
     * L'ÉQUIPE TERRAIN QUI EXÉCUTE — la notion canonique depuis le lot 3.
     *
     * `provider_team_id` pointe sur `provider_teams`, table GELÉE : aucun modèle Eloquent, aucun
     * écran, alimentée par les seuls seeders. Une équipe créée par une société dans son propre
     * espace vit dans `field_teams` et ne pouvait donc recevoir aucune mission.
     *
     * Les deux colonnes cohabitent : repointer une clé étrangère existante aurait cassé les lignes
     * qui la référencent. C'est celle-ci qu'on lit désormais.
     *
     * @return BelongsTo<FieldTeam, $this>
     */
    public function fieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class);
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

    /**
     * Les affectations qui donnent ENCORE un droit sur la mission.
     *
     * Une affectation n'est jamais supprimée : on la marque. `reassigned` quand quelqu'un prend la
     * place, `released` quand la personne quitte la société, `declined` / `expired` / `cancelled`
     * pour une offre qui n'a pas abouti. La ligne reste donc en base, et tout code qui demandait
     * seulement « existe-t-il une affectation pour cette personne ? » répondait oui pour celle
     * qu'on venait justement d'écarter.
     *
     * On EXCLUT les états terminaux au lieu d'énumérer les états actifs : un statut ajouté demain
     * garde le comportement d'aujourd'hui plutôt que de fermer une porte en silence.
     *
     * @var list<string>
     */
    public const AFFECTATIONS_ECARTEES = ['reassigned', 'released', 'cancelled', 'declined', 'expired'];

    /**
     * QUI INTERVIENT SUR CETTE MISSION — la seule réponse qui fasse autorité.
     *
     * DEUX COLONNES NOMMENT LA MÊME PERSONNE. `lead_employee_id` est l'historique (le salarié
     * désigné à la création, ce qu'écrit `MissionFromRendezVousSyncService`) ; `lead_provider_user_id`
     * est celle qu'écrit le dispatch et, surtout, la réassignation par une société.
     *
     * `MissionAssignmentService` ne mettait à jour QUE la seconde : après un changement
     * d'intervenant, `lead_employee_id` continuait de nommer la personne remplacée, et tout le
     * terrain web la lit — la politique, les tableaux d'exécution, le suivi de trajet.
     *
     * Le dispatch écrit les deux, la création n'écrit que l'historique : le repli va donc du plus
     * récent vers le plus ancien, jamais l'inverse.
     */
    public function intervenantId(): ?int
    {
        $id = $this->lead_provider_user_id ?? $this->lead_employee_id ?? null;

        return $id ? (int) $id : null;
    }

    /**
     * Cette personne a-t-elle le droit d'agir sur la mission côté terrain ?
     *
     * Responsable de la mission, ou porteuse d'une affectation encore valide. C'est la question que
     * posaient une dizaine d'endroits, chacun avec sa propre formulation — et la plupart en
     * oubliant d'écarter les affectations révoquées.
     */
    public function estIntervenant(User|int|null $utilisateur): bool
    {
        $id = $utilisateur instanceof User ? (int) $utilisateur->id : (int) ($utilisateur ?? 0);

        if ($id <= 0) {
            return false;
        }

        if ((int) ($this->lead_provider_user_id ?? 0) === $id
            || (int) ($this->lead_employee_id ?? 0) === $id) {
            return true;
        }

        return $this->assignments()
            ->where('user_id', $id)
            ->whereNotIn('assignment_status', self::AFFECTATIONS_ECARTEES)
            ->exists();
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

    /**
     * LES SEGMENTS DE CETTE MISSION (ajoutée le 2026-08-06).
     *
     * `TeamLeadOperationsService::updateMemberStatus()` appelle `$mission->taskSegments()` pour
     * recalculer l'avancement global. Seul `taskSegment()` existait — un `BelongsTo` SINGULIER,
     * via `missions.mission_task_segment_id`, qui désigne le segment dont une mission est issue.
     * Ce n'est pas la même chose : ici on veut les segments RATTACHÉS à la mission, côté inverse de
     * `MissionTaskSegment::mission()`.
     *
     * Le pluriel manquant faisait échouer toute mise à jour de statut membre par un
     * `BadMethodCallException` — le panneau chef d'équipe était inutilisable de bout en bout.
     *
     * @return HasMany<MissionTaskSegment, $this>
     */
    public function taskSegments(): HasMany
    {
        return $this->hasMany(MissionTaskSegment::class, 'mission_id');
    }
}
