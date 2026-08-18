<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DES COMPTES POUR TRAVAILLER, ET POUR VOIR CE QUE CHACUN VOIT.
 *
 * {@see QaAccountsSeeder} fournit déjà les cinq rôles du harnais visuel — dont un administrateur
 * `is_super_admin`, qui passe TOUS les gardes. C'est exactement ce qu'il faut à un harnais qui
 * balaie toutes les pages, et exactement ce qu'il ne faut pas pour comprendre ce qu'un
 * administrateur ORDINAIRE voit.
 *
 * ── POURQUOI DES COMPTES AU PÉRIMÈTRE ÉTROIT ─────────────────────────────────────────────────
 *
 * Depuis `EnforceModuleGate`, un module qui déclare une capacité masque sa tuile ET ferme sa porte.
 * Quatre-vingt-quatre modules sur quatre-vingt-six en déclarent une. Un super-administrateur ne
 * verra jamais la différence — et c'est justement la différence qu'on veut pouvoir constater avant
 * de remettre un compte à quelqu'un.
 *
 * Trois comptes, donc, et chacun répond à une question qu'on se pose pour de vrai :
 *
 *   `dev-admin@brio.test`  toutes les capacités, mais PAS super-administrateur. C'est le compte de
 *                          travail : il ouvre tout, et si un écran lui répond 403, c'est qu'une
 *                          capacité manque à sa déclaration — un super-admin aurait masqué le
 *                          défaut.
 *   `comptable@brio.test`  la comptabilité et rien d'autre. C'est le compte qu'on remet à son
 *                          comptable : on peut vérifier de ses yeux qu'il n'atteint ni les
 *                          clients, ni les prestataires, ni les paiements.
 *   `locations@brio.test`  le comptoir de location seul, pour la même raison.
 *
 * ── IDEMPOTENT, ET SANS ÉCRASER UN MOT DE PASSE CHOISI ───────────────────────────────────────
 *
 * Relancé, il ne double rien. Le mot de passe n'est posé qu'à la CRÉATION : quelqu'un qui l'a
 * changé sur son environnement ne le retrouve pas réinitialisé au prochain semis, ce qui est le
 * genre de surprise qui fait perdre une demi-heure.
 */
class ComptesDeDeveloppementSeeder extends Seeder
{
    /**
     * Le mot de passe des comptes de développement.
     *
     * Lu depuis la configuration, comme celui du harnais visuel : l'écrire ici en clair le mettrait
     * dans l'historique du dépôt, et un mot de passe versionné finit toujours par se retrouver sur
     * un environnement qui n'est plus de développement.
     */
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
                /*
                 * TOUTES LES CAPACITÉS, DÉRIVÉES DE LA SOURCE.
                 *
                 * Une liste recopiée ici prendrait du retard au premier ajout, et le compte
                 * perdrait un écran sans que personne comprenne pourquoi.
                 */
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

            /*
             * `forceFill` ET NON `updateOrCreate` : `platform_role`, `role` et `permissions` ne
             * sont pas des colonnes qu'une inscription publique doit pouvoir se poser elle-même.
             * Un semis les écrit en connaissance de cause, depuis des valeurs codées ici.
             */
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
