<?php

namespace App\Console\Commands;

use App\Models\Mission;
use App\Services\Geocoding\GeocodingService;
use Illuminate\Console\Command;

class BackfillMissionDestinations extends Command
{
    protected $signature = 'missions:backfill-destinations {--force}';
    protected $description = 'Backfill destination lat/lng for existing missions';

    public function handle(GeocodingService $geocoding): int
    {
        $query = Mission::query()
            ->with(['rendezVous.postalCode.country'])
            ->where(function ($q) {
                $q->whereNull('destination_lat')->orWhereNull('destination_lng');
            });

        $count = 0;

        $query->chunkById(100, function ($missions) use ($geocoding, &$count) {
            foreach ($missions as $mission) {
                $rdv = $mission->rendezVous;

                if (! $rdv) {
                    continue;
                }

                $countryCode = strtoupper((string) (
                    $rdv->postalCode?->country?->iso_code
                    ?? data_get($rdv->zone_snapshot, 'postal_code.country_code')
                    ?? 'BE'
                ));

                $destination = $geocoding->resolve(
                    $rdv->adresse,
                    $rdv->code_postal,
                    $rdv->ville,
                    $countryCode
                );

                if (! $destination) {
                    continue;
                }

                $mission->update([
                    'destination_lat' => $destination['lat'],
                    'destination_lng' => $destination['lng'],
                ]);

                $count++;
            }
        });

        $this->info("Destinations mises à jour : {$count}");

        return self::SUCCESS;
    }
}