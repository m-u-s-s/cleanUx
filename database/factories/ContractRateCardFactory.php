<?php

namespace Database\Factories;

use App\Models\ContractRateCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractRateCard> */
class ContractRateCardFactory extends Factory
{
    protected $model = ContractRateCard::class;

    public function definition(): array
    {
        return [
            'negotiated_unit_price_cents' => 1800,
            'currency' => 'EUR',
        ];
    }
}
