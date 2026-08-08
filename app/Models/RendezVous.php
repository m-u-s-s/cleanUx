<?php

namespace App\Models;

use Database\Factories\RendezVousFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RendezVous extends Booking
{
    /** @use HasFactory<RendezVousFactory> */
    use HasFactory;

    protected $table = 'rendez_vous';

    // Reset the inherited Booking allowlist so the restrictive $guarded blocklist below is the
    // operative rule (a non-empty $fillable would otherwise take precedence over $guarded).
    protected $fillable = [];

    // L1 — never allow mass-assignment of the privilege/money columns on this central booking
    // entity. A restrictive guard (vs the previous wide-open $guarded = []) blocks the
    // escalation vectors (forcing status, reassigning client/provider, editing amounts/payment
    // flags) while leaving harmless fields assignable. No code mass-assigns RendezVous today;
    // this is defense-in-depth against any future controller doing fill($request->all()).
    protected $guarded = [
        'id',
        'status',
        'client_id',
        'employe_id',
        'customer_user_id',
        'assigned_provider_user_id',
        'payment_status',
        'payout_status',
        'devis_estime',
        'estimated_price',
        'final_price',
        'payment_amount_cents',
        'provider_amount_cents',
        'provider_payout_cents',
        'platform_fee_cents',
    ];

    /** @return HasMany<Feedback, $this> */
    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class, 'rendez_vous_id');
    }

    /** @return HasOne<Mission, $this> */
    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class, 'rendez_vous_id')->latestOfMany();
    }

    /** @return HasOne<Mission, $this> */
    public function latestFeedback(): HasOne
    {
        return $this->feedback();
    }

    /** @return HasMany<Mission, $this> */
    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class, 'rendez_vous_id');
    }

    /** @return HasOne<Mission, $this> */
    public function mission(): HasOne
    {
        return $this->hasOne(Mission::class, 'rendez_vous_id');
    }
}
