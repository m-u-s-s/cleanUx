<?php

namespace App\Console\Commands;

use App\Jobs\Missions\GeocodeMissionDestination;
use App\Models\Mission;
use App\Services\Geocoding\GeocodingService;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;

class BackfillMissionDestinations extends Command
{
    // --force est déclaré de longue date mais n'a jamais été lu : la commande ne traite que les
    // missions sans destination, donc il n'y a rien à forcer. Conservé pour ne pas casser un
    // appel existant, sans effet.
    protected $signature = 'missions:backfill-destinations {--force}';

    protected $description = 'Backfill destination lat/lng for existing missions';

    public function handle(GeocodingService $geocoding): int
    {
        $query = Mission::query()->where(function ($q) {
            $q->whereNull('destination_lat')->orWhereNull('destination_lng');
        });

        $total = (clone $query)->count();
        $this->info("Missions sans destination : {$total}");

        $done = 0;

        $query->chunkById(100, function ($missions) use ($geocoding, &$done) {
            foreach ($missions as $mission) {
                // Réutilise la résolution du job : il gère la reprise des coordonnées déjà
                // fournies par le client et l'absence de résultat. Auparavant cette commande
                // n'interrogeait qu'une des deux colonnes vers `bookings` et sautait en silence
                // toutes les missions créées par l'autre chemin.
                $outcome = (new GeocodeMissionDestination($mission->id))->handle($geocoding);

                if (in_array($outcome, [GeocodeMissionDestination::OUTCOME_COPIED, GeocodeMissionDestination::OUTCOME_GEOCODED], true)) {
                    $done++;
                }

                // Nominatim demande au plus une requête par seconde. La commande couvre désormais
                // les deux chemins de création, donc bien plus de lignes qu'avant : sans pause,
                // un rattrapage de masse se ferait bannir. On ne temporise qu'après un appel
                // réseau réel — une destination recopiée du booking n'en déclenche aucun.
                if (in_array($outcome, [GeocodeMissionDestination::OUTCOME_GEOCODED, GeocodeMissionDestination::OUTCOME_UNRESOLVED], true)) {
                    Sleep::for(1100)->milliseconds();
                }
            }
        });

        $this->info("Destinations mises à jour : {$done}");

        return self::SUCCESS;
    }
}
