<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StatutRendezVousSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('bookings')) {
            $this->command?->warn('⚠️ Table bookings introuvable, StatutRendezVousSeeder ignoré.');
            return;
        }

        $statuses = ['pending', 'confirmed', 'cancelled'];
        $placeTypes = ['appartement', 'maison', 'bureau', 'commerce'];
        $frequencies = ['once', 'weekly', 'monthly'];
        $priorities = ['low', 'normal', 'urgent'];

        DB::table('bookings')
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use ($statuses, $placeTypes, $frequencies, $priorities) {
                foreach ($bookings as $booking) {
                    $payload = [
                        'status' => $statuses[array_rand($statuses)],
                        'updated_at' => now(),
                    ];

                    if (Schema::hasColumn('bookings', 'place_type')) {
                        $payload['place_type'] = $placeTypes[array_rand($placeTypes)];
                    }

                    if (Schema::hasColumn('bookings', 'frequency')) {
                        $payload['frequency'] = $frequencies[array_rand($frequencies)];
                    }

                    if (Schema::hasColumn('bookings', 'priority')) {
                        $payload['priority'] = $priorities[array_rand($priorities)];
                    }

                    DB::table('bookings')
                        ->where('id', $booking->id)
                        ->update($payload);
                }
            });

        $this->command?->info('✅ Statuts des bookings répartis selon les colonnes disponibles.');
    }
}