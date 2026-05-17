<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait SeedsOnlyExistingColumns
{
    protected function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    protected function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    protected function onlyExistingColumns(string $table, array $payload): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = array_flip(Schema::getColumnListing($table));

        return collect($payload)
            ->filter(fn ($value, $key) => isset($columns[$key]))
            ->map(fn ($value) => $this->normalizeSeederValue($value))
            ->all();
    }

    protected function updateOrInsertTable(string $table, array $where, array $values): ?object
    {
        if (! Schema::hasTable($table)) {
            $this->command?->warn("⚠️ Table [$table] absente, seed ignoré.");
            return null;
        }

        $where = $this->onlyExistingColumns($table, $where);
        $values = $this->onlyExistingColumns($table, $values);

        if ($where === []) {
            $this->command?->warn("⚠️ Seed [$table] ignoré : aucune colonne de recherche valide.");
            return null;
        }

        $now = now();

        if (Schema::hasColumn($table, 'created_at') && ! array_key_exists('created_at', $where) && ! array_key_exists('created_at', $values)) {
            $values['created_at'] = $now;
        }

        if (Schema::hasColumn($table, 'updated_at') && ! array_key_exists('updated_at', $values)) {
            $values['updated_at'] = $now;
        }

        DB::table($table)->updateOrInsert($where, $values);

        $row = DB::table($table)->where($where)->first();

        if ($table === 'bookings' && $row && Schema::hasTable('rendez_vous')) {
            $this->mirrorBookingToLegacyRendezVous($row);
        }

        return $row;
    }

    protected function mirrorBookingToLegacyRendezVous(object $booking): void
    {
        $columns = array_flip(Schema::getColumnListing('rendez_vous'));

        $payload = collect([
            'id'                 => $booking->id ?? null,
            'booking_reference'  => $booking->booking_reference ?? null,
            'client_id'          => $booking->client_id ?? null,
            'employe_id'         => $booking->employe_id ?? null,
            'user_id'            => $booking->client_id ?? null,
            'service_catalog_id' => $booking->service_catalog_id ?? null,
            'service_zone_id'    => $booking->service_zone_id ?? null,
            'postal_code_id'     => $booking->postal_code_id ?? null,
            'status'             => $booking->status ?? null,
            'date'               => $booking->date ?? ($booking->scheduled_date ?? null),
            'heure'              => $booking->heure ?? ($booking->scheduled_time ?? null),
            'scheduled_at'       => $booking->scheduled_at ?? null,
            'adresse'            => $booking->adresse ?? ($booking->address ?? null),
            'address'            => $booking->adresse ?? ($booking->address ?? null),
            'ville'              => $booking->ville ?? ($booking->city ?? null),
            'city'               => $booking->ville ?? ($booking->city ?? null),
            'code_postal'        => $booking->code_postal ?? ($booking->postal_code ?? null),
            'postal_code'        => $booking->code_postal ?? ($booking->postal_code ?? null),
            'zone_snapshot'      => $booking->zone_snapshot ?? null,
            'pricing_snapshot'   => $booking->pricing_snapshot ?? null,
            'estimated_price'    => $booking->estimated_price ?? ($booking->devis_estime ?? null),
            'final_price'        => $booking->final_price ?? null,
            'created_at'         => $booking->created_at ?? null,
            'updated_at'         => $booking->updated_at ?? null,
        ])->filter(fn ($value, $key) => isset($columns[$key]))->all();

        if (empty($payload['id'])) {
            return;
        }

        try {
            DB::table('rendez_vous')->updateOrInsert(['id' => $payload['id']], $payload);
        } catch (\Throwable $e) {
            // ignore : la table legacy peut avoir des contraintes FK différentes
        }
    }

    protected function insertTableRows(string $table, array $rows): int
    {
        if (! Schema::hasTable($table) || $rows === []) {
            return 0;
        }

        $now = now();
        $filteredRows = [];

        foreach ($rows as $row) {
            if (Schema::hasColumn($table, 'created_at') && ! array_key_exists('created_at', $row)) {
                $row['created_at'] = $now;
            }

            if (Schema::hasColumn($table, 'updated_at') && ! array_key_exists('updated_at', $row)) {
                $row['updated_at'] = $now;
            }

            $filtered = $this->onlyExistingColumns($table, $row);

            if ($filtered !== []) {
                $filteredRows[] = $filtered;
            }
        }

        if ($filteredRows === []) {
            return 0;
        }

        DB::table($table)->insert($filteredRows);

        return count($filteredRows);
    }

    protected function normalizeSeederValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $value;
    }
}
