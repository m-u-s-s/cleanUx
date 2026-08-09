<?php

namespace Tests\Feature\Seeding;

use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UN SEUL MOT DE PASSE POUR TOUS LES COMPTES SEMÉS.
 *
 * Chaque seeder posait le sien : « password » dans la démo plateforme, « demo2026! » et
 * « admin2026! » dans la démo production, « QaPhase2! » pour les comptes QA. Vérifier un parcours à
 * travers les rôles — client, prestataire, société, admin — demandait donc de retrouver dans quel
 * fichier chaque compte avait été créé, et le réflexe en cas de doute était de réinitialiser la
 * base. Le harnais Playwright, lui, codait la valeur QA de son côté : deux sources qui ne se
 * vérifiaient pas.
 *
 * CE TEST LIT LES SOURCES, pas la base. Semer la plateforme entière ici coûterait des minutes à
 * chaque exécution de la suite, et ne dirait rien du seeder que quelqu'un ajoutera demain. Ce qu'on
 * garde, c'est l'INVARIANT D'ÉCRITURE : aucun fichier de `database/seeders` ne doit hacher un
 * littéral. Même patron que le garde-fou de thème du dépôt.
 *
 * La fabrique `UserFactory` est délibérément HORS PÉRIMÈTRE : elle sert les tests, dont beaucoup se
 * connectent avec sa valeur par défaut. Un seeder qui l'emploie doit donc passer le mot de passe
 * explicitement — et ce test le vérifie aussi.
 */
class MotDePasseCommunDesComptesSemesTest extends TestCase
{
    #[Test]
    public function la_configuration_expose_un_mot_de_passe_de_semis(): void
    {
        $mdp = config('brio.seed.password');

        $this->assertIsString($mdp);
        $this->assertNotSame('', $mdp);

        // Le serveur exige huit caractères : un mot de passe de confort plus court produirait des
        // comptes semés sur lesquels la connexion échouerait à la validation, pas au hachage.
        $this->assertGreaterThanOrEqual(8, strlen((string) $mdp));
    }

    #[Test]
    public function aucun_seeder_ne_hache_un_mot_de_passe_en_dur(): void
    {
        $fautifs = [];

        foreach ($this->fichiersDeSemis() as $chemin) {
            $source = (string) file_get_contents($chemin);

            // `Hash::make('...')` ou `bcrypt('...')` avec un littéral : la valeur échappe alors à la
            // source unique, et personne ne le saura avant d'essayer de se connecter.
            if (preg_match_all("/(?:Hash::make|bcrypt)\(\s*'([^']*)'/", $source, $trouvailles)) {
                $fautifs[basename($chemin)] = $trouvailles[1];
            }
        }

        $this->assertSame(
            [],
            $fautifs,
            "Mot de passe en dur dans un seeder — la source unique est config('brio.seed.password') :\n"
            .json_encode($fautifs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    #[Test]
    public function un_seeder_qui_emploie_la_fabrique_pose_le_mot_de_passe(): void
    {
        $fautifs = [];

        foreach ($this->fichiersDeSemis() as $chemin) {
            $source = (string) file_get_contents($chemin);

            if (! str_contains($source, 'User::factory(')) {
                continue;
            }

            /*
             * La fabrique a sa propre valeur par défaut, qui sert les TESTS. Un compte semé qui la
             * garderait ne serait joignable qu'avec un mot de passe différent de tous les autres —
             * exactement le désordre qu'on vient de supprimer.
             */
            if (! str_contains($source, "config('brio.seed.password')")) {
                $fautifs[] = basename($chemin);
            }
        }

        $this->assertSame([], $fautifs, 'Seeder employant User::factory() sans poser le mot de passe commun.');
    }

    #[Test]
    public function le_harnais_navigateur_lit_la_meme_valeur(): void
    {
        $defaut = (string) config('brio.seed.password');

        foreach (['e2e/helpers/auth.ts', 'tools/visual-qa/modules.mjs', 'scripts/embed_sweep.php'] as $relatif) {
            $source = (string) file_get_contents(base_path($relatif));

            // Le harnais se connecte à des comptes semés : une valeur codée de son côté échouerait
            // à la connexion en accusant l'application, et non la divergence.
            $this->assertStringContainsString(
                $defaut,
                $source,
                "{$relatif} ne porte plus le mot de passe commun des comptes semés.",
            );
        }
    }

    #[Test]
    public function le_mot_de_passe_configure_est_utilisable_tel_quel(): void
    {
        $mdp = (string) config('brio.seed.password');

        // Bout en bout : ce que le seeder hachera est bien ce qu'un formulaire de connexion enverra.
        $this->assertTrue(Hash::check($mdp, Hash::make($mdp)));
    }

    /** @return list<string> */
    private function fichiersDeSemis(): array
    {
        return array_values(array_filter(
            (array) glob(database_path('seeders/*.php')),
            'is_string',
        ));
    }
}
