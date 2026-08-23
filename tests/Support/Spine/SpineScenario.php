<?php

namespace Tests\Support\Spine;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\ProviderProfile;
use App\Models\User;

/** Builds a complete, Connect-ready money-mission scenario for spine tests. */
class SpineScenario
{
    public User $client;

    public User $provider;

    public Booking $booking;

    public Mission $mission;

    private float $devis = 100.00;

    public static function make(): self
    {
        return new self;
    }

    public function withDevis(float $amount): self
    {
        $this->devis = $amount;

        return $this;
    }

    public function build(): self
    {
        // ── Client ──────────────────────────────────────────────────────────
        // stripe_id satisfies the test assertion $s->client->stripe_id (Cashier column).
        $this->client = User::factory()->client()->create([
            'stripe_id' => 'cus_test_'.uniqid(),
        ]);

        // ── Provider ────────────────────────────────────────────────────────
        // stripe_connect_account_id on the User row satisfies
        // $s->provider->stripe_connect_account_id test assertion.
        $this->provider = User::factory()->employe()->create([
            'stripe_connect_account_id' => 'acct_provider_test_'.uniqid(),
            'stripe_connect_status' => 'active',
        ]);

        // ── ProviderProfile (required for canReceiveStripeConnectPayments) ──
        // HasProviderFeatures::canReceiveStripeConnectPayments() reads
        // $this->providerProfile — if missing, it returns false immediately.
        // We mirror the same Connect values so the profile is authoritative.
        ProviderProfile::factory()->create([
            'user_id' => $this->provider->id,
            'stripe_connect_account_id' => $this->provider->stripe_connect_account_id,
            'stripe_connect_status' => 'active',  // satisfies === 'active' branch
        ]);

        // Refresh to load the providerProfile relation after creation.
        $this->provider->refresh();

        // ── Booking ─────────────────────────────────────────────────────────
        // BookingFactory wires ServiceZone + PostalCode + ServiceCatalog
        // automatically; we only override ownership + financial columns.
        $this->booking = Booking::factory()->create([
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'devis_estime' => $this->devis,
            'payment_status' => 'pending',
        ]);

        // ── Mission ──────────────────────────────────────────────────────────
        // `booking_id` est la seule clé de `missions` vers `bookings` depuis la fusion des deux
        // colonnes ; il n'y a plus de branche à choisir.
        // lead_provider_user_id is the Phase 11+ column for the independent
        // provider (distinct from legacy lead_employee_id used by orgs).
        $this->mission = Mission::factory()->create([
            'booking_id' => $this->booking->id,
            'lead_provider_user_id' => $this->provider->id,
        ]);

        return $this;
    }
}
