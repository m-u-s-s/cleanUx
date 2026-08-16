<?php

namespace App\Models\Concerns;

use App\Models\AvailabilityException;
use App\Models\AvailabilitySlot;
use App\Models\EmployeeZoneAssignment;
use App\Models\FieldTeam;
use App\Models\Mission;
use App\Models\Pivots\TradeUser;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Services\FaceCheck\FaceCheckRequirement;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

trait HasProviderFeatures
{
    /**
     * L'ANNOTATION DÉSIGNAIT `AvailabilitySlot`, la relation rend un `ProviderProfile`.
     *
     * Un copier-coller depuis la relation voisine, invisible tant que personne n'appelait cette
     * relation depuis un contexte typé : PHPStan croyait donc lire un créneau de disponibilité et
     * annonçait `commission_rate` inexistante — sur le calcul de commission, précisément.
     *
     * @return HasOne<ProviderProfile, $this>
     */
    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class);
    }

    /** @return HasMany<AvailabilitySlot, $this> */
    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(AvailabilitySlot::class, 'provider_user_id');
    }

    /** @return HasMany<AvailabilityException, $this> */
    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class, 'provider_user_id');
    }

    /**
     * Les métiers déclarés par ce prestataire.
     *
     * L'annotation disait `EmployeeZoneAssignment` — recopiée de `serviceZones()` juste en dessous.
     * L'analyse statique décrivait donc ces lignes comme des affectations de zone : toute lecture
     * d'une colonne de métier y était signalée comme inexistante, et une vraie erreur s'y serait
     * cachée au milieu du bruit.
     *
     * @return BelongsToMany<Trade, $this, TradeUser, 'pivot'>
     */
    public function trades(): BelongsToMany
    {
        return $this->belongsToMany(Trade::class, 'trade_user')
            ->using(TradeUser::class)
            ->withPivot(['is_primary', 'proficiency', 'notes'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<EmployeeZoneAssignment, $this> */
    public function serviceZones(): BelongsToMany
    {
        return $this->belongsToMany(
            ServiceZone::class,
            'employee_zone_assignments',
            'user_id',
            'service_zone_id'
        )->withPivot([
            'assignment_type',
            'coverage_priority',
            'is_active',
            'starts_at',
            'ends_at',
            'notes',
        ])->withTimestamps();
    }

    /** @return HasMany<ServiceZone, $this> */
    public function zoneAssignments(): HasMany
    {
        return $this->hasMany(EmployeeZoneAssignment::class, 'user_id');
    }

    /** @return BelongsToMany<ServiceZone, $this> */
    public function fieldTeams(): BelongsToMany
    {
        return $this->belongsToMany(FieldTeam::class, 'field_team_members')
            ->withPivot(['role_on_team', 'is_team_lead', 'is_active', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /** @return BelongsTo<ServiceZone, $this> */
    public function managedServiceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'managed_service_zone_id');
    }

    /** @return BelongsTo<ServiceZone, $this> */
    public function primaryServiceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'primary_service_zone_id');
    }

    public function activeServiceZones(): BelongsToMany
    {
        return $this->serviceZones()
            ->wherePivot('is_active', true);
    }

    public function leadMissions()
    {
        $query = Mission::query();

        if (Schema::hasColumn('missions', 'team_lead_user_id')) {
            return $query->where('team_lead_user_id', $this->id);
        }

        if (Schema::hasColumn('missions', 'lead_user_id')) {
            return $query->where('lead_user_id', $this->id);
        }

        if (
            Schema::hasTable('mission_team_assignments')
            && Schema::hasColumn('mission_team_assignments', 'mission_id')
            && Schema::hasColumn('mission_team_assignments', 'user_id')
        ) {
            return $query->whereIn('id', function ($sub) {
                $sub->from('mission_team_assignments')
                    ->select('mission_id')
                    ->where('user_id', $this->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function activeLedFieldTeams()
    {
        $query = FieldTeam::query();

        if (Schema::hasColumn('field_teams', 'lead_user_id')) {
            return $query->where('lead_user_id', $this->id)
                ->when(Schema::hasColumn('field_teams', 'is_active'), fn ($q) => $q->where('is_active', true));
        }

        if (Schema::hasColumn('field_teams', 'team_lead_user_id')) {
            return $query->where('team_lead_user_id', $this->id)
                ->when(Schema::hasColumn('field_teams', 'is_active'), fn ($q) => $q->where('is_active', true));
        }

        if (
            Schema::hasTable('field_team_members')
            && Schema::hasColumn('field_team_members', 'field_team_id')
            && Schema::hasColumn('field_team_members', 'user_id')
        ) {
            return $query->whereIn('id', function ($sub) {
                $sub->from('field_team_members')
                    ->select('field_team_id')
                    ->where('user_id', $this->id)
                    ->where(function ($q) {
                        if (Schema::hasColumn('field_team_members', 'role')) {
                            $q->whereIn('role', ['lead', 'leader', 'team_lead']);
                        }
                    });
            })->when(Schema::hasColumn('field_teams', 'is_active'), fn ($q) => $q->where('is_active', true));
        }

        return $query->whereRaw('1 = 0');
    }

    public function isFieldTeamLead(): bool
    {
        return $this->activeLedFieldTeams()->exists();
    }

    /** @return BelongsToMany<self, $this> */
    public function preferredByClients(): BelongsToMany
    {
        if (! Schema::hasTable('client_provider_preferences')) {
            return $this->belongsToMany(
                self::class,
                'client_provider_preferences',
                'provider_user_id',
                'client_user_id'
            )->whereRaw('1 = 0');
        }

        return $this->belongsToMany(
            self::class,
            'client_provider_preferences',
            'provider_user_id',
            'client_user_id'
        )->withTimestamps();
    }

    /**
     * Le prestataire peut-il recevoir des fonds par Stripe Connect ?
     *
     * Les colonnes `stripe_connect_*` existent sur `users` ET sur `provider_profiles`. Une seule
     * est alimentée : StripeConnectService écrit sur `users`, et rien n'écrit jamais sur le
     * profil. Cette méthode ne lisait pourtant que le profil — elle rendait donc `false` pour
     * TOUT prestataire, y compris un compte Stripe pleinement configuré, et
     * MissionPaymentService::authorize() refusait par conséquent chaque autorisation de paiement.
     *
     * Le défaut était masqué par son propre test, dont le fixture renseigne les deux tables : une
     * forme qui ne se produit jamais en production. On lit donc `users` en premier — la seule
     * source réellement écrite — sans cesser d'accepter le profil, que d'anciens environnements
     * ont pu remplir.
     */
    public function canReceiveStripeConnectPayments(): bool
    {
        if ($this->isStripeConnectActive($this)) {
            return true;
        }

        $profile = $this->providerProfile;

        return $profile !== null && $this->isStripeConnectActive($profile);
    }

    /**
     * Un compte existe dès le début du parcours Stripe : seuls `active` ou une date
     * d'aboutissement attestent qu'il peut effectivement recevoir des fonds.
     */
    private function isStripeConnectActive(object $source): bool
    {
        return ! empty($source->stripe_connect_account_id)
            && (($source->stripe_connect_status ?? null) === 'active'
                || ($source->stripe_connect_onboarded_at ?? null) !== null);
    }

    /**
     * CE PRESTATAIRE EST-IL SOUMIS AU CONTRÔLE FACIAL ?
     *
     * Sert la visibilité de la case de menu, et rien d'autre : la garde, elle, vit dans
     * `FaceCheckGate`, appelé par le middleware et par les six autres points de passage. Une
     * condition d'affichage n'est jamais une autorisation — le menu dit ce qu'on peut voir, pas ce
     * qu'on peut faire.
     *
     * Soft-fail : un module absent, une table pas encore migrée, une config cassée ne doivent pas
     * faire tomber le rendu de la navigation entière pour une case sur trente.
     */
    public function estSoumisAuControleFacial(): bool
    {
        try {
            return app(FaceCheckRequirement::class)->appliesToProvider($this);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * KYC = BLOCAGE STRICT (décision produit 2026-06-11) : un prestataire ne peut
     * recevoir/accepter une mission que si sa vérification d'identité est validée.
     */
    public function hasClearedKyc(): bool
    {
        return (bool) $this->providerProfile?->isVerified();
    }
}
