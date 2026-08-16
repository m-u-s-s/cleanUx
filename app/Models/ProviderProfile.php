<?php

namespace App\Models;

use App\Enums\ProviderType;
use Database\Factories\ProviderProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Colonnes KYC ajoutées par `2026_05_18_140003_add_kyc_fields_to_provider_profiles`. Elles
 * existent en base et sont écrites par `KycVerificationService`, mais n'étaient déclarées nulle
 * part : toute lecture depuis un autre module était donc invisible à l'analyse statique.
 *
 * @property ?string $kyc_provider
 * @property ?string $kyc_external_applicant_id
 * @property ?int $kyc_last_verification_id
 * @property ?Carbon $kyc_completed_at
 * @property ?string $kyc_score
 * @property ?Carbon $self_registered_at
 */
class ProviderProfile extends Model
{
    /** @use HasFactory<ProviderProfileFactory> */
    use HasFactory;

    /**
     * SECURITY : Tous les setters sensibles (verification_status, stripe_connect_status, etc.)
     * doivent passer par un Service composant un payload explicite après validation.
     * NE JAMAIS faire `ProviderProfile::find($id)->update($request->all())` dans un controller —
     * utiliser `$request->validated()` ou forceFill() depuis un Service.
     */
    protected $fillable = [
        'user_id',
        'organization_account_id',
        'provider_type',
        'status',
        'verification_status',
        'hourly_rate',
        'commission_rate',
        'default_slot_duration',
        'current_lat',
        'current_lng',
        'last_location_at',
        'stripe_connect_account_id',
        'stripe_connect_status',
        'stripe_connect_onboarded_at',
        'skills',
        'settings',
        'metadata',
        'bio',
        'photo_path',

        'onboarding_step',
        'onboarding_completed_at',
        'onboarding_started_at',
        'verified_at',
        'verification_notes',
        'is_online',
        'went_online_at',
        'went_offline_at',
        'last_heartbeat_at',
        'battery_level',

        'rating_avg',
        'rating_count',
        'rating_distribution',
        'rating_dimensions',
        'rating_last_at',

        // ÉCRITES PAR LE CODE, ÉCARTÉES PAR ELOQUENT. Ces colonnes existent en base et des
        // appels d'écriture les renseignent, mais leur absence de cette liste les faisait
        // disparaître SANS ERREUR — Eloquent écarte en silence ce qu'il ne peut pas assigner.
        // Écrit par ProviderPresenceService à chaque passage en ligne et à chaque battement de
        // cœur, et jeté en silence depuis toujours : le contexte de présence (appareil, précision)
        // n'a jamais atteint la base.
        'presence_meta',
    ];

    protected $casts = [
        'provider_type' => ProviderType::class,
        'hourly_rate' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'current_lat' => 'decimal:7',
        'current_lng' => 'decimal:7',
        'last_location_at' => 'datetime',
        // Volontairement absent de $fillable : cette colonne PORTE la restriction des comptes
        // auto-inscrits. La rendre assignable en masse permettrait de l'effacer, donc de lever
        // sa propre restriction. Elle se pose par forceFill, à la création du compte.
        'self_registered_at' => 'datetime',
        'stripe_connect_onboarded_at' => 'datetime',
        'skills' => 'array',
        'settings' => 'array',
        'metadata' => 'array',

        'onboarding_step' => 'integer',
        'onboarding_completed_at' => 'datetime',
        'onboarding_started_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_online' => 'boolean',
        'went_online_at' => 'datetime',
        'went_offline_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'battery_level' => 'integer',

        'rating_avg' => 'decimal:2',
        'rating_count' => 'integer',
        'rating_distribution' => 'array',
        'rating_dimensions' => 'array',
        'rating_last_at' => 'datetime',
    ];

    // Relations
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    // Helpers
    public function isIndependent(): bool
    {
        return $this->provider_type === ProviderType::INDEPENDENT;
    }

    public function isCompanyWorker(): bool
    {
        return $this->provider_type === ProviderType::COMPANY_WORKER;
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isStripeConnected(): bool
    {
        return filled($this->stripe_connect_account_id)
            && $this->stripe_connect_status === 'active';
    }

    public function updateLocation(float $lat, float $lng): void
    {
        $this->update([
            'current_lat' => $lat,
            'current_lng' => $lng,
            'last_location_at' => now(),
        ]);
    }
}
