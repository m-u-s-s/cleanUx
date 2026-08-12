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
        $this->seedUser('demo@brio.com', [
            'name' => 'Marie Demo', 'password' => Hash::make((string) config('brio.seed.password')),
            'role' => 'client', 'is_active' => true, 'locale' => 'fr',
        ]);

        // Demo providers (5 trades)
        $trades = ['Nettoyage', 'Peinture', 'Babysitting', 'Jardinage', 'Plomberie'];
        foreach ($trades as $i => $trade) {
            $this->seedUser("provider{$i}@brio.com", [
                'name' => "Provider {$trade}", 'password' => Hash::make((string) config('brio.seed.password')),
                'role' => 'provider', 'is_active' => true, 'locale' => 'fr',
            ]);
        }

        // Demo admin
        $this->seedUser('admin@brio.com', [
            'name' => 'Admin Brio', 'password' => Hash::make((string) config('brio.seed.password')),
            'role' => 'admin', 'is_active' => true, 'is_super_admin' => true, 'locale' => 'fr',
        ]);

        $this->command->info('Demo users seeded: demo@brio.com / provider0-4@brio.com / admin@brio.com');
    }

    /**
     * `forceFill` ET NON `firstOrCreate` : `role` n'est plus assignable en masse — c'est une
     * colonne d'élévation que l'inscription publique ne doit jamais pouvoir se poser. Un semis
     * l'écrit volontairement, depuis des valeurs codées ici.
     *
     * @param  array<string, mixed>  $attributs
     */
    private function seedUser(string $email, array $attributs): User
    {
        $utilisateur = User::firstOrNew(['email' => $email]);

        if (! $utilisateur->exists) {
            $utilisateur->forceFill($attributs)->save();
        }

        return $utilisateur;
    }
}
