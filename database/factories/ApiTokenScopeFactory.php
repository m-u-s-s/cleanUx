<?php

namespace Database\Factories;

use App\Models\ApiTokenScope;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiTokenScope>
 */
class ApiTokenScopeFactory extends Factory
{
    protected $model = ApiTokenScope::class;

    public function definition(): array
    {
        $verb     = fake()->randomElement(['read', 'write', 'list', 'delete']);
        $resource = fake()->randomElement(['bookings', 'missions', 'users', 'payouts', 'disputes']);
        $code     = "{$verb}:{$resource}:" . Str::random(4);

        return [
            'code'          => $code,
            'name'          => ucfirst("{$verb} {$resource}"),
            'description'   => "Allows {$verb} access to {$resource}",
            'category'      => fake()->randomElement([
                ApiTokenScope::CATEGORY_READ,
                ApiTokenScope::CATEGORY_WRITE,
                ApiTokenScope::CATEGORY_ADMIN,
            ]),
            'required_role'  => null,
            'is_active'      => true,
            'is_dangerous'   => false,
        ];
    }

    public function admin(): static
    {
        return $this->state([
            'category'     => ApiTokenScope::CATEGORY_ADMIN,
            'is_dangerous' => true,
        ]);
    }
}
