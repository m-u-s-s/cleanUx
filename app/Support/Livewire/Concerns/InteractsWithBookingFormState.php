<?php

namespace App\Support\Livewire\Concerns;

use App\Support\Livewire\Concerns\Booking\ConfiguresRecurringBooking;
use App\Support\Livewire\Concerns\Booking\InteractsWithBookingOrganization;
use App\Support\Livewire\Concerns\Booking\InteractsWithBookingPrefill;
use App\Support\Livewire\Concerns\Booking\ProvidesBookingFormOptions;
use App\Support\Livewire\Concerns\Booking\ResolvesBookingZoneCoverage;

/**
 * Aggregator trait for the core client booking form state.
 *
 * Historically this was a single 842-line "god trait". It has been split into
 * focused, cohesive concern-traits (same namespace family, under Concerns\Booking)
 * and recomposed here. PHP flattens composed traits into the consuming class
 * identically, so every method/property still lands on the component exactly as
 * before — this aggregation is behavior-preserving.
 *
 * Consumed by exactly one component: App\Livewire\Client\PrendreRendezVous.
 */
trait InteractsWithBookingFormState
{
    use ConfiguresRecurringBooking;
    use InteractsWithBookingOrganization;
    use InteractsWithBookingPrefill;
    use ProvidesBookingFormOptions;
    use ResolvesBookingZoneCoverage;
}
