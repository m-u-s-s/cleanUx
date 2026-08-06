<?php

namespace Database\Factories;

use App\Models\OrganizationAccount;
use App\Models\SigningAppointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SigningAppointment> */
class SigningAppointmentFactory extends Factory
{
    protected $model = SigningAppointment::class;

    public function definition(): array
    {
        return [
            'organization_account_id' => OrganizationAccount::factory(),
            'signer_user_id' => User::factory(),
            'scheduled_at' => now()->addDays(fake()->numberBetween(1, 20)),
            'status' => SigningAppointment::STATUT_PLANIFIE,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function signe(): static
    {
        return $this->state(fn () => [
            'status' => SigningAppointment::STATUT_SIGNE,
            'completed_at' => now(),
        ]);
    }
}
