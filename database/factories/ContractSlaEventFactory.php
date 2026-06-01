<?php

namespace Database\Factories;

use App\Models\ContractSlaEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractSlaEvent> */
class ContractSlaEventFactory extends Factory
{
    protected $model = ContractSlaEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'kind' => ContractSlaEvent::KIND_RESPONSE,
            'due_at' => now()->addHours(4),
            'status' => ContractSlaEvent::STATUS_PENDING,
        ];
    }
}
