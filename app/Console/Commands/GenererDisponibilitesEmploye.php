<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Disponibilite;
use Carbon\Carbon;

class GenererDisponibilitesEmploye extends Command
{
    protected $signature = 'dispo:generer {employe_id}';
    protected $description = 'Génère des créneaux de test pour un employé';

    public function handle()
    {
        $employe = User::find($this->argument('employe_id'));

        if (!$employe || $employe->role !== 'employe') {
            $this->error("Aucun employé trouvé avec cet ID.");
            return;
        }

        $heures = ['08:30', '09:30', '10:30', '11:30', '14:00', '15:00'];
        $today = Carbon::today();

        foreach (range(0, 6) as $offset) {
            $jour = $today->copy()->addDays($offset);
            foreach ($heures as $heure) {
                Disponibilite::firstOrCreate([
                    'user_id' => $employe->id,
                    'date' => $jour->toDateString(),
                    'heure_debut' => $heure,
                    'heure_fin' => Carbon::parse($heure)
                        ->addMinutes($employe->duree_creneau)
                        ->format('H:i'),
                ]);
            }
        }

        $this->info("✅ Créneaux générés pour {$employe->name}");
    }
}
