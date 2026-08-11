<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\OrganizationAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_account_id' => OrganizationAccount::factory(),
            'name' => 'Dégraissant multi-surfaces',
            'unit' => 'bidon',
            'quantity' => 10,
            'reorder_threshold' => 2,
            'unit_cost_cents' => 450,
            'is_billable' => false,
            'is_active' => true,
        ];
    }
}
