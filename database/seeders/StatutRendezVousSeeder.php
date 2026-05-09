<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Booking;

class StatutRendezVousSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = ['confirme', 'refuse', 'en_attente'];

        Booking::query()->chunkById(100, function ($rdvs) use ($statuses) {
            foreach ($rdvs as $rdv) {
                $rdv->update([
                    'status' => $statuses[array_rand($statuses)],
                ]);
            }
        });

        $this->command->info('✅ Les statuts des rendez-vous ont été répartis aléatoirement.');
    }
}
