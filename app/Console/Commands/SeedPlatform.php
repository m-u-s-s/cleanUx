<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

class SeedPlatform extends Command
{
    protected $signature = 'app:seed-platform
        {profile=demo : Profil de seed à charger (demo, reference, production)}
        {--fresh : Exécute migrate:fresh --seed avec le profil choisi}
        {--force : Force l\'exécution en environnement protégé}';

    protected $description = 'Exécute le seed CleanUx avec un profil explicite et cohérent (demo, reference, production).';

    public function handle(): int
    {
        $profile = strtolower((string) $this->argument('profile'));
        $allowedProfiles = config('cleanux.seed.allowed_profiles', ['demo', 'reference', 'production']);

        if (! in_array($profile, $allowedProfiles, true)) {
            $this->components->error('Profil invalide. Valeurs autorisées : '.implode(', ', $allowedProfiles).'.');

            return self::FAILURE;
        }

        config()->set('cleanux.seed.profile', $profile);

        $this->components->info("Lancement du seed CleanUx avec le profil [$profile].");

        if ($this->option('fresh')) {
            $exitCode = $this->call('migrate:fresh', [
                '--seed' => true,
                '--force' => (bool) $this->option('force'),
            ]);
        } else {
            $exitCode = $this->call('db:seed', [
                '--class' => DatabaseSeeder::class,
                '--force' => (bool) $this->option('force'),
            ]);
        }

        if ($exitCode !== 0) {
            return $exitCode;
        }

        $this->call('app:prepare-fresh-seed');

        return self::SUCCESS;
    }
}
