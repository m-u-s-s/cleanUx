<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['code' => 'EUR', 'name' => 'Euro',              'symbol' => '€', 'decimals' => 2, 'sort_order' => 1],
            ['code' => 'USD', 'name' => 'US Dollar',         'symbol' => '$', 'decimals' => 2, 'sort_order' => 2],
            ['code' => 'GBP', 'name' => 'British Pound',     'symbol' => '£', 'decimals' => 2, 'sort_order' => 3],
            ['code' => 'CHF', 'name' => 'Swiss Franc',       'symbol' => 'Fr','decimals' => 2, 'sort_order' => 4],
            ['code' => 'CAD', 'name' => 'Canadian Dollar',   'symbol' => '$', 'decimals' => 2, 'sort_order' => 5],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => '$', 'decimals' => 2, 'sort_order' => 6],
            ['code' => 'JPY', 'name' => 'Japanese Yen',      'symbol' => '¥', 'decimals' => 0, 'sort_order' => 7],
            ['code' => 'NOK', 'name' => 'Norwegian Krone',   'symbol' => 'kr','decimals' => 2, 'sort_order' => 8],
            ['code' => 'SEK', 'name' => 'Swedish Krona',     'symbol' => 'kr','decimals' => 2, 'sort_order' => 9],
            ['code' => 'DKK', 'name' => 'Danish Krone',      'symbol' => 'kr','decimals' => 2, 'sort_order' => 10],
            ['code' => 'PLN', 'name' => 'Polish Zloty',      'symbol' => 'zł','decimals' => 2, 'sort_order' => 11],
            ['code' => 'CZK', 'name' => 'Czech Koruna',      'symbol' => 'Kč','decimals' => 2, 'sort_order' => 12],
        ];

        foreach ($defaults as $cur) {
            Currency::query()->updateOrCreate(['code' => $cur['code']], array_merge($cur, ['is_active' => true]));
        }
    }
}
