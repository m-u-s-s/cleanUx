<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Feedback;
use App\Models\User;
use App\Models\RendezVous;

class FeedbackSeeder extends Seeder
{
    public function run(): void
    {
        $clientsExist = User::clientFacing()->exists();
        $employesExist = User::where('role', 'employe')->exists();

        if (!$clientsExist || !$employesExist) {
            $this->command->warn('⚠️ Pas de clients ou d’employés pour générer les feedbacks.');
            return;
        }

        $rdvs = RendezVous::whereIn('status', ['confirme', 'refuse'])
            ->whereDoesntHave('feedback')
            ->inRandomOrder()
            ->take(10)
            ->get(['id', 'client_id']);

        if ($rdvs->isEmpty()) {
            $this->command->warn('⚠️ Aucun rendez-vous disponible pour générer des feedbacks.');
            return;
        }

        $now = now();
        $rows = [];

        foreach ($rdvs as $rdv) {
            $rows[] = [
                'client_id' => $rdv->client_id,
                'rendez_vous_id' => $rdv->id,
                'note' => fake()->numberBetween(2, 5),
                'commentaire' => fake()->paragraph(1),
                'reponse_admin' => fake()->boolean() ? fake()->sentence() : null,
                'created_at' => $now->copy()->subDays(rand(0, 30)),
                'updated_at' => $now,
            ];
        }

        Feedback::insert($rows);

        $this->command->info('✅ FeedbackSeeder exécuté : feedbacks générés.');
    }
}