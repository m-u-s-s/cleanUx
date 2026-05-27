<?php

namespace Database\Factories;

use App\Models\EnterpriseWorkOrder;
use App\Models\WorkOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderLineFactory extends Factory
{
    protected $model = WorkOrderLine::class;

    public function definition(): array
    {
        $quantity  = fake()->randomFloat(2, 1, 10);
        $unitPrice = fake()->randomFloat(2, 20, 500);

        return [
            'enterprise_work_order_id' => fn () => EnterpriseWorkOrder::factory()->create()->id,
            'service_catalog_id'       => null,
            'title'                    => fake()->words(3, true),
            'description'              => fake()->sentence(),
            'quantity'                 => $quantity,
            'unit'                     => fake()->randomElement(['hour', 'm2', 'unit', 'day']),
            'unit_price'               => $unitPrice,
            'line_total'               => round($quantity * $unitPrice, 2),
            'surface_value'            => null,
            'metadata'                 => null,
        ];
    }
}
