<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\SeedsOnlyExistingColumns;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BelgiumGeographySeeder extends Seeder
{
    use SeedsOnlyExistingColumns;

    public function run(): void
    {
        $country = $this->updateOrInsertTable(
            'countries',
            ['iso_code' => 'BE'],
            [
                'iso3_code' => 'BEL',
                'name' => 'Belgique',
                'official_name' => 'Royaume de Belgique',
                'default_locale' => 'fr_BE',
                'currency_code' => 'EUR',
                'currency' => 'EUR',
                'phone_code' => '+32',
                'timezone' => 'Europe/Brussels',
                'booking_enabled' => true,
                'market_stage' => 'active',
                'settings' => ['locale' => 'fr_BE', 'timezone' => 'Europe/Brussels'],
                'is_active' => true,
            ]
        );

        if (! $country) {
            return;
        }

        $regions = collect();
        if (Schema::hasTable('regions')) {
            $regions = collect($this->regions())->mapWithKeys(function (array $region) use ($country) {
                $model = $this->updateOrInsertTable(
                    'regions',
                    ['country_id' => $country->id, 'code' => $region['code']],
                    [
                        'name' => $region['name'],
                        'slug' => Str::slug($region['name']),
                        'sort_order' => $region['sort_order'],
                        'is_active' => true,
                    ]
                );

                return $model ? [$region['code'] => $model] : [];
            });
        }

        $provinces = collect();
        if (Schema::hasTable('provinces')) {
            $provinces = collect($this->provinces())->mapWithKeys(function (array $province) use ($country, $regions) {
                $model = $this->updateOrInsertTable(
                    'provinces',
                    ['country_id' => $country->id, 'code' => $province['code']],
                    [
                        'region_id' => $regions[$province['region']]->id ?? null,
                        'name' => $province['name'],
                        'slug' => Str::slug($province['name']),
                        'sort_order' => $province['sort_order'],
                        'is_active' => true,
                    ]
                );

                return $model ? [$province['code'] => $model] : [];
            });
        }

        $communes = collect();
        if (Schema::hasTable('communes')) {
            $communes = collect($this->communes())->mapWithKeys(function (array $commune) use ($country, $regions, $provinces) {
                $model = $this->updateOrInsertTable(
                    'communes',
                    [
                        'country_id' => $country->id,
                        'province_id' => $provinces[$commune['province']]->id ?? null,
                        'name' => $commune['name'],
                    ],
                    [
                        'region_id' => $regions[$commune['region']]->id ?? null,
                        'nis_code' => $commune['nis_code'],
                        'slug' => Str::slug($commune['name']),
                        'is_active' => true,
                    ]
                );

                return $model ? [$commune['name'] => $model] : [];
            });
        }

        foreach ($this->postalCodes() as $postalCode) {
            $this->updateOrInsertTable(
                'postal_codes',
                [
                    'country_id' => $country->id,
                    'code' => $postalCode['code'],
                    'city_name' => $postalCode['city_name'],
                ],
                [
                    'region_id' => $regions[$postalCode['region']]->id ?? null,
                    'province_id' => $provinces[$postalCode['province']]->id ?? null,
                    'commune_id' => $communes[$postalCode['commune']]->id ?? null,
                    'lat' => $postalCode['latitude'] ?? null,
                    'lng' => $postalCode['longitude'] ?? null,
                    'latitude' => $postalCode['latitude'] ?? null,
                    'longitude' => $postalCode['longitude'] ?? null,
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('✅ Géographie Belgique initialisée selon les colonnes réellement migrées.');
    }

    protected function regions(): array
    {
        return [
            ['code' => 'BE-BRU', 'name' => 'Bruxelles-Capitale', 'sort_order' => 10],
            ['code' => 'BE-VLG', 'name' => 'Flandre', 'sort_order' => 20],
            ['code' => 'BE-WAL', 'name' => 'Wallonie', 'sort_order' => 30],
        ];
    }

    protected function provinces(): array
    {
        return [
            ['code' => 'BRU', 'name' => 'Bruxelles-Capitale', 'region' => 'BE-BRU', 'sort_order' => 10],
            ['code' => 'ANT', 'name' => 'Anvers', 'region' => 'BE-VLG', 'sort_order' => 20],
            ['code' => 'VBR', 'name' => 'Brabant flamand', 'region' => 'BE-VLG', 'sort_order' => 30],
            ['code' => 'OVL', 'name' => 'Flandre-Orientale', 'region' => 'BE-VLG', 'sort_order' => 40],
            ['code' => 'WVL', 'name' => 'Flandre-Occidentale', 'region' => 'BE-VLG', 'sort_order' => 50],
            ['code' => 'LIM', 'name' => 'Limbourg', 'region' => 'BE-VLG', 'sort_order' => 60],
            ['code' => 'WBR', 'name' => 'Brabant wallon', 'region' => 'BE-WAL', 'sort_order' => 70],
            ['code' => 'HAI', 'name' => 'Hainaut', 'region' => 'BE-WAL', 'sort_order' => 80],
            ['code' => 'LIE', 'name' => 'Liège', 'region' => 'BE-WAL', 'sort_order' => 90],
            ['code' => 'LUX', 'name' => 'Luxembourg', 'region' => 'BE-WAL', 'sort_order' => 100],
            ['code' => 'NAM', 'name' => 'Namur', 'region' => 'BE-WAL', 'sort_order' => 110],
        ];
    }

    protected function communes(): array
    {
        return [
            ['name' => 'Bruxelles', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21004'],
            ['name' => 'Ixelles', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21009'],
            ['name' => 'Uccle', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21016'],
            ['name' => 'Schaerbeek', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21015'],
            ['name' => 'Anderlecht', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21001'],
            ['name' => 'Saint-Gilles', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21013'],
            ['name' => 'Jette', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21010'],
            ['name' => 'Evere', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21006'],
            ['name' => 'Etterbeek', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21005'],
            ['name' => 'Woluwe-Saint-Lambert', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21018'],
            ['name' => 'Woluwe-Saint-Pierre', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21019'],
            ['name' => 'Auderghem', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21002'],
            ['name' => 'Molenbeek-Saint-Jean', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21012'],
            ['name' => 'Saint-Josse-ten-Noode', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21014'],
            ['name' => 'Berchem-Sainte-Agathe', 'province' => 'BRU', 'region' => 'BE-BRU', 'nis_code' => '21003'],
            ['name' => 'Anvers', 'province' => 'ANT', 'region' => 'BE-VLG', 'nis_code' => '11002'],
            ['name' => 'Malines', 'province' => 'ANT', 'region' => 'BE-VLG', 'nis_code' => '12025'],
            ['name' => 'Louvain', 'province' => 'VBR', 'region' => 'BE-VLG', 'nis_code' => '24062'],
            ['name' => 'Gand', 'province' => 'OVL', 'region' => 'BE-VLG', 'nis_code' => '44021'],
            ['name' => 'Aalst', 'province' => 'OVL', 'region' => 'BE-VLG', 'nis_code' => '41002'],
            ['name' => 'Saint-Nicolas', 'province' => 'OVL', 'region' => 'BE-VLG', 'nis_code' => '46021'],
            ['name' => 'Bruges', 'province' => 'WVL', 'region' => 'BE-VLG', 'nis_code' => '31005'],
            ['name' => 'Courtrai', 'province' => 'WVL', 'region' => 'BE-VLG', 'nis_code' => '34022'],
            ['name' => 'Ostende', 'province' => 'WVL', 'region' => 'BE-VLG', 'nis_code' => '35013'],
            ['name' => 'Hasselt', 'province' => 'LIM', 'region' => 'BE-VLG', 'nis_code' => '71022'],
            ['name' => 'Genk', 'province' => 'LIM', 'region' => 'BE-VLG', 'nis_code' => '71016'],
            ['name' => 'Wavre', 'province' => 'WBR', 'region' => 'BE-WAL', 'nis_code' => '25112'],
            ['name' => 'Nivelles', 'province' => 'WBR', 'region' => 'BE-WAL', 'nis_code' => '25072'],
            ['name' => 'Mons', 'province' => 'HAI', 'region' => 'BE-WAL', 'nis_code' => '53053'],
            ['name' => 'Charleroi', 'province' => 'HAI', 'region' => 'BE-WAL', 'nis_code' => '52011'],
            ['name' => 'Tournai', 'province' => 'HAI', 'region' => 'BE-WAL', 'nis_code' => '57081'],
            ['name' => 'La Louvière', 'province' => 'HAI', 'region' => 'BE-WAL', 'nis_code' => '55022'],
            ['name' => 'Liège', 'province' => 'LIE', 'region' => 'BE-WAL', 'nis_code' => '62063'],
            ['name' => 'Verviers', 'province' => 'LIE', 'region' => 'BE-WAL', 'nis_code' => '63079'],
            ['name' => 'Arlon', 'province' => 'LUX', 'region' => 'BE-WAL', 'nis_code' => '81001'],
            ['name' => 'Namur', 'province' => 'NAM', 'region' => 'BE-WAL', 'nis_code' => '92094'],
        ];
    }

    protected function postalCodes(): array
    {
        return [
            ['code' => '1000', 'city_name' => 'Bruxelles', 'commune' => 'Bruxelles', 'province' => 'BRU', 'region' => 'BE-BRU', 'latitude' => 50.8503400, 'longitude' => 4.3517100],
            ['code' => '1000', 'city_name' => 'Brussel', 'commune' => 'Bruxelles', 'province' => 'BRU', 'region' => 'BE-BRU', 'latitude' => 50.8503400, 'longitude' => 4.3517100],
            ['code' => '1000', 'city_name' => 'Brussels', 'commune' => 'Bruxelles', 'province' => 'BRU', 'region' => 'BE-BRU', 'latitude' => 50.8503400, 'longitude' => 4.3517100],
            ['code' => '1030', 'city_name' => 'Schaerbeek', 'commune' => 'Schaerbeek', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1030', 'city_name' => 'Schaarbeek', 'commune' => 'Schaerbeek', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1040', 'city_name' => 'Etterbeek', 'commune' => 'Etterbeek', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1050', 'city_name' => 'Ixelles', 'commune' => 'Ixelles', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1050', 'city_name' => 'Elsene', 'commune' => 'Ixelles', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1060', 'city_name' => 'Saint-Gilles', 'commune' => 'Saint-Gilles', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1060', 'city_name' => 'Sint-Gillis', 'commune' => 'Saint-Gilles', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1070', 'city_name' => 'Anderlecht', 'commune' => 'Anderlecht', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1080', 'city_name' => 'Molenbeek-Saint-Jean', 'commune' => 'Molenbeek-Saint-Jean', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1080', 'city_name' => 'Sint-Jans-Molenbeek', 'commune' => 'Molenbeek-Saint-Jean', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1082', 'city_name' => 'Berchem-Sainte-Agathe', 'commune' => 'Berchem-Sainte-Agathe', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1082', 'city_name' => 'Sint-Agatha-Berchem', 'commune' => 'Berchem-Sainte-Agathe', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1090', 'city_name' => 'Jette', 'commune' => 'Jette', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1140', 'city_name' => 'Evere', 'commune' => 'Evere', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1150', 'city_name' => 'Woluwe-Saint-Pierre', 'commune' => 'Woluwe-Saint-Pierre', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1150', 'city_name' => 'Sint-Pieters-Woluwe', 'commune' => 'Woluwe-Saint-Pierre', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1160', 'city_name' => 'Auderghem', 'commune' => 'Auderghem', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1160', 'city_name' => 'Oudergem', 'commune' => 'Auderghem', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1180', 'city_name' => 'Uccle', 'commune' => 'Uccle', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1180', 'city_name' => 'Ukkel', 'commune' => 'Uccle', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1200', 'city_name' => 'Woluwe-Saint-Lambert', 'commune' => 'Woluwe-Saint-Lambert', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1200', 'city_name' => 'Sint-Lambrechts-Woluwe', 'commune' => 'Woluwe-Saint-Lambert', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1210', 'city_name' => 'Saint-Josse-ten-Noode', 'commune' => 'Saint-Josse-ten-Noode', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '1210', 'city_name' => 'Sint-Joost-ten-Node', 'commune' => 'Saint-Josse-ten-Noode', 'province' => 'BRU', 'region' => 'BE-BRU'],
            ['code' => '2000', 'city_name' => 'Anvers', 'commune' => 'Anvers', 'province' => 'ANT', 'region' => 'BE-VLG', 'latitude' => 51.2194480, 'longitude' => 4.4024640],
            ['code' => '2000', 'city_name' => 'Antwerpen', 'commune' => 'Anvers', 'province' => 'ANT', 'region' => 'BE-VLG', 'latitude' => 51.2194480, 'longitude' => 4.4024640],
            ['code' => '2000', 'city_name' => 'Antwerp', 'commune' => 'Anvers', 'province' => 'ANT', 'region' => 'BE-VLG', 'latitude' => 51.2194480, 'longitude' => 4.4024640],
            ['code' => '2018', 'city_name' => 'Antwerpen', 'commune' => 'Anvers', 'province' => 'ANT', 'region' => 'BE-VLG'],
            ['code' => '2060', 'city_name' => 'Antwerpen', 'commune' => 'Anvers', 'province' => 'ANT', 'region' => 'BE-VLG'],
            ['code' => '2800', 'city_name' => 'Malines', 'commune' => 'Malines', 'province' => 'ANT', 'region' => 'BE-VLG'],
            ['code' => '2800', 'city_name' => 'Mechelen', 'commune' => 'Malines', 'province' => 'ANT', 'region' => 'BE-VLG'],
            ['code' => '3000', 'city_name' => 'Louvain', 'commune' => 'Louvain', 'province' => 'VBR', 'region' => 'BE-VLG'],
            ['code' => '3000', 'city_name' => 'Leuven', 'commune' => 'Louvain', 'province' => 'VBR', 'region' => 'BE-VLG'],
            ['code' => '3500', 'city_name' => 'Hasselt', 'commune' => 'Hasselt', 'province' => 'LIM', 'region' => 'BE-VLG'],
            ['code' => '3600', 'city_name' => 'Genk', 'commune' => 'Genk', 'province' => 'LIM', 'region' => 'BE-VLG'],
            ['code' => '4000', 'city_name' => 'Liège', 'commune' => 'Liège', 'province' => 'LIE', 'region' => 'BE-WAL', 'latitude' => 50.6325570, 'longitude' => 5.5796660],
            ['code' => '4000', 'city_name' => 'Liege', 'commune' => 'Liège', 'province' => 'LIE', 'region' => 'BE-WAL', 'latitude' => 50.6325570, 'longitude' => 5.5796660],
            ['code' => '4000', 'city_name' => 'Luik', 'commune' => 'Liège', 'province' => 'LIE', 'region' => 'BE-WAL', 'latitude' => 50.6325570, 'longitude' => 5.5796660],
            ['code' => '4800', 'city_name' => 'Verviers', 'commune' => 'Verviers', 'province' => 'LIE', 'region' => 'BE-WAL'],
            ['code' => '5000', 'city_name' => 'Namur', 'commune' => 'Namur', 'province' => 'NAM', 'region' => 'BE-WAL'],
            ['code' => '5000', 'city_name' => 'Namen', 'commune' => 'Namur', 'province' => 'NAM', 'region' => 'BE-WAL'],
            ['code' => '6000', 'city_name' => 'Charleroi', 'commune' => 'Charleroi', 'province' => 'HAI', 'region' => 'BE-WAL'],
            ['code' => '6700', 'city_name' => 'Arlon', 'commune' => 'Arlon', 'province' => 'LUX', 'region' => 'BE-WAL'],
            ['code' => '6700', 'city_name' => 'Aarlen', 'commune' => 'Arlon', 'province' => 'LUX', 'region' => 'BE-WAL'],
            ['code' => '7000', 'city_name' => 'Mons', 'commune' => 'Mons', 'province' => 'HAI', 'region' => 'BE-WAL'],
            ['code' => '7000', 'city_name' => 'Bergen', 'commune' => 'Mons', 'province' => 'HAI', 'region' => 'BE-WAL'],
            ['code' => '7100', 'city_name' => 'La Louvière', 'commune' => 'La Louvière', 'province' => 'HAI', 'region' => 'BE-WAL'],
            ['code' => '7100', 'city_name' => 'La Louviere', 'commune' => 'La Louvière', 'province' => 'HAI', 'region' => 'BE-WAL'],
            ['code' => '7500', 'city_name' => 'Tournai', 'commune' => 'Tournai', 'province' => 'HAI', 'region' => 'BE-WAL'],
            ['code' => '7500', 'city_name' => 'Doornik', 'commune' => 'Tournai', 'province' => 'HAI', 'region' => 'BE-WAL'],
            ['code' => '8000', 'city_name' => 'Bruges', 'commune' => 'Bruges', 'province' => 'WVL', 'region' => 'BE-VLG'],
            ['code' => '8000', 'city_name' => 'Brugge', 'commune' => 'Bruges', 'province' => 'WVL', 'region' => 'BE-VLG'],
            ['code' => '8400', 'city_name' => 'Ostende', 'commune' => 'Ostende', 'province' => 'WVL', 'region' => 'BE-VLG'],
            ['code' => '8400', 'city_name' => 'Oostende', 'commune' => 'Ostende', 'province' => 'WVL', 'region' => 'BE-VLG'],
            ['code' => '8500', 'city_name' => 'Courtrai', 'commune' => 'Courtrai', 'province' => 'WVL', 'region' => 'BE-VLG'],
            ['code' => '8500', 'city_name' => 'Kortrijk', 'commune' => 'Courtrai', 'province' => 'WVL', 'region' => 'BE-VLG'],
            ['code' => '9000', 'city_name' => 'Gand', 'commune' => 'Gand', 'province' => 'OVL', 'region' => 'BE-VLG', 'latitude' => 51.0543420, 'longitude' => 3.7174240],
            ['code' => '9000', 'city_name' => 'Gent', 'commune' => 'Gand', 'province' => 'OVL', 'region' => 'BE-VLG', 'latitude' => 51.0543420, 'longitude' => 3.7174240],
            ['code' => '9000', 'city_name' => 'Ghent', 'commune' => 'Gand', 'province' => 'OVL', 'region' => 'BE-VLG', 'latitude' => 51.0543420, 'longitude' => 3.7174240],
            ['code' => '9100', 'city_name' => 'Saint-Nicolas', 'commune' => 'Saint-Nicolas', 'province' => 'OVL', 'region' => 'BE-VLG'],
            ['code' => '9100', 'city_name' => 'Sint-Niklaas', 'commune' => 'Saint-Nicolas', 'province' => 'OVL', 'region' => 'BE-VLG'],
            ['code' => '9300', 'city_name' => 'Aalst', 'commune' => 'Aalst', 'province' => 'OVL', 'region' => 'BE-VLG'],
            ['code' => '1300', 'city_name' => 'Wavre', 'commune' => 'Wavre', 'province' => 'WBR', 'region' => 'BE-WAL'],
            ['code' => '1300', 'city_name' => 'Waver', 'commune' => 'Wavre', 'province' => 'WBR', 'region' => 'BE-WAL'],
            ['code' => '1400', 'city_name' => 'Nivelles', 'commune' => 'Nivelles', 'province' => 'WBR', 'region' => 'BE-WAL'],
            ['code' => '1400', 'city_name' => 'Nijvel', 'commune' => 'Nivelles', 'province' => 'WBR', 'region' => 'BE-WAL'],
        ];
    }
}
