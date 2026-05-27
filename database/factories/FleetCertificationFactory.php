<?php

namespace Database\Factories;

use App\Models\FleetCertification;
use App\Models\FleetVehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FleetCertificationFactory extends Factory
{
    protected $model = FleetCertification::class;

    public function definition(): array
    {
        $vehicle   = FleetVehicle::factory()->create();
        $issuedAt  = now()->subYear();
        $expiresAt = now()->addYear();

        return [
            'subject_type'       => FleetCertification::SUBJECT_VEHICLE,
            'subject_id'         => $vehicle->id,
            'certification_type' => fake()->randomElement(['insurance', 'technical_check', 'driver_license']),
            'reference'          => Str::upper(Str::random(10)),
            'issued_at'          => $issuedAt->toDateString(),
            'expires_at'         => $expiresAt->toDateString(),
            'issuing_authority'  => fake()->company(),
            'document_path'      => 'certifications/' . Str::uuid() . '.pdf',
            'status'             => FleetCertification::STATUS_ACTIVE,
            'created_by_user_id' => User::factory(),
            'metadata'           => [],
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMonth()->toDateString(),
            'status'     => FleetCertification::STATUS_EXPIRED,
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->addDays(15)->toDateString(),
            'status'     => FleetCertification::STATUS_EXPIRING_SOON,
        ]);
    }

    public function forProvider(): static
    {
        return $this->state(fn () => [
            'subject_type' => FleetCertification::SUBJECT_PROVIDER,
            'subject_id'   => User::factory(),
        ]);
    }
}
