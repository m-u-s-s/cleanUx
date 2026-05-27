<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\TripTrackingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripTrackingSessionFactory extends Factory
{
    protected $model = TripTrackingSession::class;

    public function definition(): array
    {
        return [
            'code' => TripTrackingSession::generateCode(),
            'booking_id' => fn () => Booking::factory()->create()->id,
            'provider_user_id' => fn () => User::factory()->employe()->create()->id,
            'status' => TripTrackingSession::STATUS_ENROUTE,
            'destination_lat' => fake()->latitude(49.5, 51.5),
            'destination_lng' => fake()->longitude(2.5, 6.5),
            'geofence_radius_m' => 150,
            'start_lat' => fake()->latitude(49.5, 51.5),
            'start_lng' => fake()->longitude(2.5, 6.5),
            'points_count' => 0,
            'total_distance_m' => 0,
            'current_eta_seconds' => null,
            'last_lat' => null,
            'last_lng' => null,
            'last_speed_mps' => null,
            'metadata' => null,
            'started_at' => now(),
            'arrived_at' => null,
            'in_mission_at' => null,
            'ended_at' => null,
            'last_ping_at' => null,
        ];
    }

    public function arrived(): static
    {
        return $this->state(fn () => [
            'status' => TripTrackingSession::STATUS_ARRIVED,
            'arrived_at' => now(),
        ]);
    }

    public function inMission(): static
    {
        return $this->state(fn () => [
            'status' => TripTrackingSession::STATUS_IN_MISSION,
            'arrived_at' => now()->subMinutes(5),
            'in_mission_at' => now(),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'status' => TripTrackingSession::STATUS_ENDED,
            'arrived_at' => now()->subHour(),
            'in_mission_at' => now()->subMinutes(50),
            'ended_at' => now(),
        ]);
    }
}
