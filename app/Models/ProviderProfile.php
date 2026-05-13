<?php

namespace App\Models;

use App\Enums\ProviderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderProfile extends Model
{
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
    
        'onboarding_step',
        'onboarding_completed_at',
        'onboarding_started_at',
        'verified_at',
        'verification_notes',];

    protected $casts = [
        'provider_type'               => ProviderType::class,
        'hourly_rate'                 => 'decimal:2',
        'commission_rate'             => 'decimal:2',
        'current_lat'                 => 'decimal:7',
        'current_lng'                 => 'decimal:7',
        'last_location_at'            => 'datetime',
        'stripe_connect_onboarded_at' => 'datetime',
        'skills'                      => 'array',
        'settings'                    => 'array',
        'metadata'                    => 'array',
    
        'onboarding_step' => 'integer',
        'onboarding_completed_at' => 'datetime',
        'onboarding_started_at' => 'datetime',
        'verified_at' => 'datetime',];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
            'current_lat'      => $lat,
            'current_lng'      => $lng,
            'last_location_at' => now(),
        ]);
    }
}
