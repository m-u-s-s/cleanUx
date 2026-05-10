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

        return DB::table($table)->where($where)->first();
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
