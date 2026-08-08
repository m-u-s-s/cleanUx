<?php

namespace Database\Factories;

use App\Models\AsapDispatchNotification;
use App\Models\AsapDispatchRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AsapDispatchNotification> */
class AsapDispatchNotificationFactory extends Factory
{
    protected $model = AsapDispatchNotification::class;

    public function definition(): array
    {
        return [
            'asap_dispatch_request_id' => AsapDispatchRequest::factory(),
            'user_id' => User::factory(),
        ];
    }
}
