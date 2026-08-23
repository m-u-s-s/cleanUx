<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** DES COMPTES POUR TRAVAILLER, ET POUR VOIR CE QUE CHACUN VOIT. */
class ComptesDeDeveloppementSeeder extends Seeder
{
    /** Le mot de passe des comptes de développement. */
    private function motDePasse(): string
    {
        return (string) config('brio.seed.password', 'password');
    }

    public function run(): void
    {
        $toutes = array_keys(User::allowedAdminPermissions());

        $comptes = [
            [
                'email' => 'dev-admin@brio.test',
                'name' => 'Dev — Administrateur complet',
                // TOUTES LES CAPACITÉS, DÉRIVÉES DE LA SOURCE.
                'permissions' => $toutes,
            ],
            [
                'email' => 'comptable@brio.test',
                'name' => 'Dev — Comptable',
                'permissions' => ['manage-accounting'],
            ],
            [
                'email' => 'locations@brio.test',
                'name' => 'Dev — Comptoir de location',
                'permissions' => ['manage-rentals'],
            ],
        ];

        foreach ($comptes as $compte) {
            $utilisateur = User::firstOrNew(['email' => $compte['email']]);
            $creation = ! $utilisateur->exists;

            // `forceFill` ET NON `updateOrCreate` : `platform_role`, `role` et `permissions` ne sont pas des colonnes qu'une inscription publique doit pouvoir se poser elle-même.
            $attributs = [
                'name' => $compte['name'],
                'platform_role' => 'admin',
                'role' => 'admin',
                // PAS super-administrateur, et c'est tout l'intérêt : celui-là passe tous les
                // gardes et ne montrerait jamais ce qu'un périmètre restreint donne à voir.
                'is_super_admin' => false,
                'permissions' => $compte['permissions'],
                'status' => 'active',
                'is_active' => true,
                'account_type' => 'client_personal',
                'locale' => 'fr_BE',
                'timezone' => 'Europe/Brussels',
                'email_verified_at' => now(),
                // Un numéro dérivé de l'adresse, donc stable d'un semis à l'autre : plusieurs
                // parcours exigent un téléphone et bloquent sans lui.
                'phone' => '+3247'.substr(sprintf('%07d', crc32($compte['email']) % 10000000), 0, 7),
            ];

            if ($creation) {
                $attributs['password'] = Hash::make($this->motDePasse());
            }

            $utilisateur->forceFill($attributs)->save();
        }

        $this->command?->info(sprintf(
            'Comptes de développement : %s — mot de passe « %s ».',
            implode(', ', array_column($comptes, 'email')),
            $this->motDePasse(),
        ));
    }
}
