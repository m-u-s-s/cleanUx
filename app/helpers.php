<?php

use App\Models\Parametre;
use App\Models\User;
use App\Services\FeatureFlag\FeatureFlagService;

if (! function_exists('parametre')) {
    function parametre($cle, $default = null)
    {
        return Parametre::where('cle', $cle)->value('valeur') ?? $default;
    }
}

if (! function_exists('feature')) {
    /**
     * Check whether a feature flag is enabled.
     *
     * @param  string  $flag  Feature key from config/features.php
     * @param  User|null  $user  User to evaluate percentage/role/list rules against.
     *                           Defaults to the currently authenticated user.
     */
    function feature(string $flag, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return app(FeatureFlagService::class)->isEnabled($flag, $user);
    }
}
