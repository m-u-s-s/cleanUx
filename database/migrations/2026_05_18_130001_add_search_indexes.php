<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index additionnels pour les filtres de recherche client.
 *
 * - provider_profiles : composite (status, verification_status, rating_avg) pour
 *   les listes filtrées par rating
 * - service_catalogs : (is_active, is_featured, sort_order) pour grilles
 * - postal_codes : (city_name) pour autocomplétion (préfixe LIKE)
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->tryAddIndex('provider_profiles',
            ['status', 'verification_status', 'rating_avg'],
            'provider_profiles_search_index');

        $this->tryAddIndex('provider_profiles',
            ['hourly_rate'],
            'provider_profiles_hourly_rate_index');

        $this->tryAddIndex('service_catalogs',
            ['is_active', 'is_featured', 'sort_order'],
            'service_catalogs_search_index');

        $this->tryAddIndex('service_catalogs',
            ['trade_id', 'is_active'],
            'service_catalogs_trade_active_index');

        $this->tryAddIndex('postal_codes',
            ['city_name'],
            'postal_codes_city_name_index');

        $this->tryAddIndex('postal_codes',
            ['country_id', 'is_active'],
            'postal_codes_country_active_index');
    }

    public function down(): void
    {
        foreach ([
            ['provider_profiles', 'provider_profiles_search_index'],
            ['provider_profiles', 'provider_profiles_hourly_rate_index'],
            ['service_catalogs', 'service_catalogs_search_index'],
            ['service_catalogs', 'service_catalogs_trade_active_index'],
            ['postal_codes', 'postal_codes_city_name_index'],
            ['postal_codes', 'postal_codes_country_active_index'],
        ] as [$table, $name]) {
            $this->tryDropIndex($table, $name);
        }
    }

    protected function tryAddIndex(string $table, array $columns, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                $t->index($columns, $name);
            });
        } catch (\Throwable $e) {
            // index déjà présent — ignore
        }
    }

    protected function tryDropIndex(string $table, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($name) {
                $t->dropIndex($name);
            });
        } catch (\Throwable $e) {
        }
    }
};
