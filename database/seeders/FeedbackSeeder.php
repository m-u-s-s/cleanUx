<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsOnlyExistingColumns;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeedbackSeeder extends Seeder
{
    use SeedsOnlyExistingColumns;

    public function run(): void
    {
        if (! Schema::hasTable('feedbacks') || ! Schema::hasTable('bookings')) {
            $this->command?->warn('⚠️ Tables feedbacks/bookings absentes, feedbacks ignorés.');
            return;
        }

        $rdvs = DB::table('bookings')
            ->whereIn('status', ['confirme', 'confirmed', 'termine', 'completed'])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('feedbacks')
                    ->whereColumn('feedbacks.rendez_vous_id', 'bookings.id');
            })
            ->limit(10)
            ->get(['id', 'client_id', 'customer_user_id', 'customer_organization_id']);

        if ($rdvs->isEmpty()) {
            $this->command?->warn('⚠️ Aucun rendez-vous disponible pour générer des feedbacks.');
            return;
        }

        $rows = [];

        foreach ($rdvs as $rdv) {
            $rows[] = [
                'client_id' => $rdv->client_id ?? $rdv->customer_user_id,
                'client_user_id' => $rdv->customer_user_id ?? $rdv->client_id,
                'client_organization_id' => $rdv->customer_organization_id ?? null,
                'booking_id' => $rdv->id,
                'rendez_vous_id' => $rdv->id,
                'note' => fake()->numberBetween(3, 5),
                'commentaire' => fake()->paragraph(1),
                'feedback' => fake()->paragraph(1),
                'reponse_admin' => fake()->boolean() ? fake()->sentence() : null,
                'status' => 'published',
                'metadata' => ['seeded' => true],
            ];
        }

        $count = $this->insertTableRows('feedbacks', $rows);

        $this->command?->info("✅ FeedbackSeeder exécuté : {$count} feedback(s) généré(s).");
    }
}
