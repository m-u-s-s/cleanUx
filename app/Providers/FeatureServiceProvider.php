<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class FeatureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Feature::define('client-mobile-v2', function (User $user) {
            $betaUserIds = config('beta.client_mobile_v2_users', []);
            $allowAll = config('beta.client_mobile_v2_all', false);

            return $allowAll || in_array($user->id, $betaUserIds, true);
        });
    }
}
