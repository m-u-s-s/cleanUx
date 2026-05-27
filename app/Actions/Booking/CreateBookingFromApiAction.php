<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\Mission;
use Illuminate\Support\Str;

/**
 * CreateBookingFromApiAction
 *
 * Encapsulates the simplified mobile-API booking creation flow.
 * Extracted from ClientBookingController::store() to keep the controller thin.
 *
 * @see App\Http\Controllers\Api\Client\ClientBookingController
 */
final class CreateBookingFromApiAction
{
    /**
     * Execute the booking creation.
     *
     * @param  \App\Models\User  $user        Authenticated client
     * @param  array             $data        Validated request data
     * @return Booking                        The freshly created (and re-fetched) booking
     */
    public function execute(object $user, array $data): Booking
    {
        $now    = now();
        $isAsap = ($data['booking_mode'] ?? 'scheduled') === 'asap';

        $booking = Booking::create([
            'booking_reference'        => $this->generateReference(),
            'customer_user_id'         => $user->id,
            'client_id'                => $user->id,
            'customer_organization_id' => $user->organization_account_id ?? $user->current_organization_id ?? null,
            'service_catalog_id'       => $data['service_catalog_id'],
            'address'                  => $data['address'],
            'city'                     => $data['city'],
            'postal_code'              => $data['postal_code'],
            'country'                  => $data['country'] ?? 'BE',
            'scheduled_date'           => $data['scheduled_date'],
            'scheduled_time'           => $data['scheduled_time'] . ':00',
            'booking_mode'             => $isAsap ? 'asap' : 'scheduled',
            'status'                   => $isAsap ? 'confirme' : 'en_attente',
            'priority'                 => $data['priority'] ?? ($isAsap ? 'urgent' : 'normal'),
            'surface_m2'               => $data['surface_m2'] ?? null,
            'customer_comment'         => $data['customer_comment'] ?? null,
            'contact_name'             => $data['contact_name'] ?? $user->name,
            'contact_phone'            => $data['contact_phone'] ?? ($user->phone ?? null),
            'destination_lat'          => $data['destination_lat'] ?? null,
            'destination_lng'          => $data['destination_lng'] ?? null,
            'currency'                 => $user->preferred_currency ?? 'EUR',
            'created_by'               => $user->id,
            'asap_requested_at'        => $isAsap ? $now : null,
            'asap_deadline_at'         => $isAsap ? $now->copy()->addHours(2) : null,
        ]);

        if ($isAsap) {
            $this->maybeDispatchAsap($booking, $now);
        }

        return $booking->fresh();
    }

    /**
     * Create a planned Mission and attempt auto-dispatch for ASAP bookings.
     */
    private function maybeDispatchAsap(Booking $booking, \Carbon\Carbon $now): void
    {
        if (! class_exists(Mission::class)) {
            return;
        }

        $mission = Mission::create([
            'booking_id'       => $booking->id,
            'status'           => 'planned',
            'planned_start_at' => $now->copy()->addMinutes(30),
        ]);

        $dispatchClass = '\App\Services\Dispatch\MissionDispatchService';
        if (! class_exists($dispatchClass)) {
            return;
        }

        try {
            app($dispatchClass)->dispatchToNextProvider($mission);
        } catch (\Throwable $e) {
            \Log::warning('Auto-dispatch failed', [
                'mission_id' => $mission->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function generateReference(): string
    {
        do {
            $ref = 'CUX-' . strtoupper(bin2hex(random_bytes(3)));
        } while (Booking::where('booking_reference', $ref)->exists());

        return $ref;
    }
}
