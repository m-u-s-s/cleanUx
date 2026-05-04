<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rendez_vous')
            ->select(['id', 'service_catalog_id', 'service_type', 'pricing_snapshot'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                $catalogIds = collect($rows)
                    ->pluck('service_catalog_id')
                    ->filter()
                    ->unique()
                    ->values();

                $catalogs = DB::table('service_catalogs')
                    ->whereIn('id', $catalogIds)
                    ->get(['id', 'code', 'slug'])
                    ->keyBy('id');

                foreach ($rows as $row) {
                    $snapshot = $row->pricing_snapshot;

                    if (is_string($snapshot)) {
                        $snapshot = json_decode($snapshot, true);
                    }

                    $snapshot = is_array($snapshot) ? $snapshot : [];

                    $service = is_array(data_get($snapshot, 'service'))
                        ? data_get($snapshot, 'service')
                        : [];

                    $catalog = $catalogs->get($row->service_catalog_id);

                    $serviceIdentifier = data_get($snapshot, 'service_identifier')
                        ?: data_get($service, 'service_identifier')
                        ?: data_get($service, 'code')
                        ?: data_get($service, 'slug')
                        ?: ($catalog->code ?? null)
                        ?: ($catalog->slug ?? null)
                        ?: $row->service_type;

                    if (! $serviceIdentifier) {
                        continue;
                    }

                    $snapshot['service_identifier'] = $serviceIdentifier;

                    if (! is_array($service)) {
                        $service = [];
                    }

                    $service['service_identifier'] = $service['service_identifier'] ?? $serviceIdentifier;
                    unset($service['service_type']);

                    $snapshot['service'] = $service;

                    DB::table('rendez_vous')
                        ->where('id', $row->id)
                        ->update([
                            'pricing_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Pas de rollback destructif utile.
    }
};
