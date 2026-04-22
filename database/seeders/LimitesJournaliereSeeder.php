<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\LimiteJournaliere;

class LimitesJournaliereSeeder extends Seeder
{
    public function run(): void
    {
        $employeIds = User::where('role', 'employe')->pluck('id');

        if ($employeIds->isEmpty()) {
            $this->command->warn('⚠️ Aucun employé trouvé pour générer les limites journalières.');
            return;
        }

        $debutSemaine = now()->startOfWeek();
        $now = now();
        $rows = [];

        foreach ($employeIds as $employeId) {
            foreach (range(0, 6) as $i) {
                $rows[] = [
                    'user_id' => $employeId,
                    'date' => $debutSemaine->copy()->addDays($i)->toDateString(),
                    'limite' => fake()->numberBetween(2, 4),
                    'verrou_admin' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        LimiteJournaliere::upsert(
            $rows,
            ['user_id', 'date'],
            ['limite', 'verrou_admin', 'updated_at']
        );

        $this->command->info('✅ Limites journalières générées pour les employés.');
    }
}