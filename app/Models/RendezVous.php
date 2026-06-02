<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RendezVous extends Booking
{
    protected $table = 'rendez_vous';

    protected $guarded = [];

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
