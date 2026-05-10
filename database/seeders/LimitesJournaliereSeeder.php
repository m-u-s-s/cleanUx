<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsOnlyExistingColumns;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LimitesJournaliereSeeder extends Seeder
{
    use SeedsOnlyExistingColumns;

    public function run(): void
    {
        $targetTable = Schema::hasTable('limites_journalieres') ? 'limites_journalieres' : 'provider_daily_limits';

        if (! Schema::hasTable($targetTable)) {
            $this->command?->warn('⚠️ Aucune table de limites journalières trouvée.');
            return;
        }

        $providerIds = DB::table('users')
            ->where(function ($query) {
                if (Schema::hasColumn('users', 'platform_role')) {
                    $query->orWhereIn('platform_role', ['provider', 'employe', 'employee']);
                }

                if (Schema::hasColumn('users', 'role')) {
                    $query->orWhere('role', 'employe');
                }
            })
            ->pluck('id');

        if ($providerIds->isEmpty()) {
            $providerIds = DB::table('provider_profiles')->pluck('user_id');
        }

        if ($providerIds->isEmpty()) {
            $this->command?->warn('⚠️ Aucun prestataire trouvé pour générer les limites journalières.');
            return;
        }

        $startOfWeek = now()->startOfWeek();

        foreach ($providerIds as $providerId) {
            foreach (range(0, 6) as $i) {
                $date = $startOfWeek->copy()->addDays($i)->toDateString();

                if ($targetTable === 'provider_daily_limits') {
                    $this->updateOrInsertTable(
                        $targetTable,
                        ['provider_user_id' => $providerId, 'date' => $date],
                        [
                            'max_bookings' => fake()->numberBetween(2, 4),
                            'max_minutes' => 420,
                            'locked_by_admin' => false,
                        ]
                    );
                } else {
                    $this->updateOrInsertTable(
                        $targetTable,
                        ['user_id' => $providerId, 'date' => $date],
                        [
                            'limite' => fake()->numberBetween(2, 4),
                            'verrou_admin' => false,
                        ]
                    );
                }
            }
        }

        $this->command?->info('✅ Limites journalières générées selon la table disponible.');
    }
}
