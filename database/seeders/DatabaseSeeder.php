<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use InvalidArgumentException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $profile = $this->resolveProfile();

        match ($profile) {
            'demo' => $this->seedDemoProfile(),
            'reference' => $this->seedReferenceProfile(),
            'production' => $this->seedProductionProfile(),
            default => throw new InvalidArgumentException("Seed profile [$profile] non supporté."),
        };
    }

    protected function resolveProfile(): string
    {
        $explicitProfile = config('cleanux.seed.profile');
        $defaultProfile = config('cleanux.seed.default_profile', app()->environment('production') ? 'production' : 'demo');
        $allowedProfiles = config('cleanux.seed.allowed_profiles', ['demo', 'reference', 'production']);

        $profile = strtolower((string) ($explicitProfile ?: $defaultProfile));

        if (! in_array($profile, $allowedProfiles, true)) {
            throw new InvalidArgumentException("Seed profile [$profile] non autorisé.");
        }

        return $profile;
    }

    protected function seedDemoProfile(): void
    {
        $this->call([
            ReferencePlatformSeeder::class,
            DemoPlatformBootstrapSeeder::class,
        ]);

        $this->command?->info('✅ DatabaseSeeder terminé : profil demo chargé.');
    }

    protected function seedReferenceProfile(): void
    {
        $this->call([
            ReferencePlatformSeeder::class,
        ]);

        $this->command?->info('✅ DatabaseSeeder terminé : profil reference chargé sans données démo.');
    }

    protected function seedProductionProfile(): void
    {
        $this->call([
            ProductionBootstrapSeeder::class,
        ]);

        $this->command?->info('✅ DatabaseSeeder terminé : profil production chargé sans données démo.');
    }
}
