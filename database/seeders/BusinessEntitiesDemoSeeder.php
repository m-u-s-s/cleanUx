<?php

namespace Database\Seeders;

use App\Models\BusinessEntity;
use Illuminate\Database\Seeder;

class BusinessEntitiesDemoSeeder extends Seeder
{
    public function run(): void
    {
        BusinessEntity::query()->updateOrCreate(
            ['country_code' => 'FR', 'identifier_type' => 'siret', 'identifier_value' => '12345678900012'],
            [
                'code' => BusinessEntity::generateCode(),
                'legal_name' => 'Acme Cleaning SARL (Demo)',
                'trade_name' => 'Acme Cleaning',
                'vat_id' => 'FR12345678900',
                'legal_form' => 'SARL',
                'registered_address' => [
                    'street' => '12 rue de Paris', 'postal' => '75001', 'city' => 'Paris', 'country' => 'FR',
                ],
                'incorporation_date' => '2018-03-15',
                'contact_email' => 'demo@acme-cleaning.example.com',
                'status' => BusinessEntity::STATUS_PENDING,
                'risk_score' => 0,
                'risk_level' => BusinessEntity::RISK_LOW,
            ],
        );

        BusinessEntity::query()->updateOrCreate(
            ['country_code' => 'BE', 'identifier_type' => 'kbo', 'identifier_value' => '0123456789'],
            [
                'code' => BusinessEntity::generateCode(),
                'legal_name' => 'Demo Belgian Cleaning BVBA',
                'vat_id' => 'BE0123456789',
                'legal_form' => 'BVBA',
                'registered_address' => [
                    'street' => 'Rue Royale 50', 'postal' => '1000', 'city' => 'Bruxelles', 'country' => 'BE',
                ],
                'incorporation_date' => '2020-06-01',
                'contact_email' => 'demo@belgian-cleaning.example.com',
                'status' => BusinessEntity::STATUS_PENDING,
                'risk_score' => 0,
                'risk_level' => BusinessEntity::RISK_LOW,
            ],
        );
    }
}
