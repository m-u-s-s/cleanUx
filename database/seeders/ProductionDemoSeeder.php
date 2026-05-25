<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Demo client
        $client = User::firstOrCreate(['email' => 'demo@cleanux.com'], [
            'name' => 'Marie Demo', 'password' => Hash::make('demo2026!'),
            'role' => 'client', 'is_active' => true, 'locale' => 'fr',
        ]);

        // Demo providers (5 trades)
        $trades = ['Nettoyage', 'Peinture', 'Babysitting', 'Jardinage', 'Plomberie'];
        foreach ($trades as $i => $trade) {
            User::firstOrCreate(['email' => "provider{$i}@cleanux.com"], [
                'name' => "Provider {$trade}", 'password' => Hash::make('demo2026!'),
                'role' => 'provider', 'is_active' => true, 'locale' => 'fr',
            ]);
        }

        // Demo admin
        User::firstOrCreate(['email' => 'admin@cleanux.com'], [
            'name' => 'Admin CleanUx', 'password' => Hash::make('admin2026!'),
            'role' => 'admin', 'is_active' => true, 'is_super_admin' => true, 'locale' => 'fr',
        ]);

        $this->command->info('Demo users seeded: demo@cleanux.com / provider0-4@cleanux.com / admin@cleanux.com');
    }
}
