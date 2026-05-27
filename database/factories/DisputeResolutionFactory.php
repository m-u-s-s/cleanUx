<?php

namespace Database\Factories;

use App\Models\ComplaintCase;
use App\Models\DisputeResolution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisputeResolution>
 */
class DisputeResolutionFactory extends Factory
{
    protected $model = DisputeResolution::class;

    public function definition(): array
    {
        return [
            'complaint_case_id'     => ComplaintCase::factory(),
            'resolution_type'       => fake()->randomElement([
                DisputeResolution::TYPE_REFUND_FULL,
                DisputeResolution::TYPE_REFUND_PARTIAL,
                DisputeResolution::TYPE_CREDIT,
                DisputeResolution::TYPE_NO_ACTION,
            ]),
            'amount'                => fake()->optional(0.6)->randomFloat(2, 10, 500),
            'currency'              => 'EUR',
            'explanation'           => fake()->paragraph(),
            'issued_by_user_id'     => User::factory()->admin(),
            'status'                => DisputeResolution::STATUS_PROPOSED,
            'external_ref'          => null,
            'stripe_refund_id'      => null,
            'promo_code_id'         => null,
            'replacement_booking_id' => null,
            'applied_at'            => null,
            'failed_at'             => null,
            'failure_reason'        => null,
            'metadata'              => null,
        ];
    }

    public function applied(): static
    {
        return $this->state([
            'status'     => DisputeResolution::STATUS_APPLIED,
            'applied_at' => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }
}
