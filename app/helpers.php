if (!function_exists('parametre')) {
    function parametre($cle, $default = null) {
        return \App\Models\Parametre::where('cle', $cle)->value('valeur') ?? $default;
    }
}

if (!function_exists('feature')) {
    /**
     * Check whether a feature flag is enabled.
     *
     * @param  string                $flag  Feature key from config/features.php
     * @param  \App\Models\User|null $user  User to evaluate percentage/role/list rules against.
     *                                      Defaults to the currently authenticated user.
     */
    function feature(string $flag, ?\App\Models\User $user = null): bool
    {
        $user ??= auth()->user();
        return app(\App\Services\FeatureFlag\FeatureFlagService::class)->isEnabled($flag, $user);
    }
}

