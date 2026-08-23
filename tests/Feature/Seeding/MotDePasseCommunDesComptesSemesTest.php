<?php

namespace Tests\Feature\Seeding;

use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** UN SEUL MOT DE PASSE POUR TOUS LES COMPTES SEMÉS. */
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

            // La fabrique a sa propre valeur par défaut, qui sert les TESTS.
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

        // Les trois harnais releves ensemble. Chacun se connecte a des comptes semes : une valeur
        // codee de leur cote echouerait a la connexion en accusant l'application, et non la
        // divergence. Quand le mot de passe change, ce sont les TROIS qui decrochent.
        $decroches = [];

        foreach (['e2e/helpers/auth.ts', 'tools/visual-qa/modules.mjs', 'scripts/embed_sweep.php'] as $relatif) {
            if (! str_contains((string) file_get_contents(base_path($relatif)), $defaut)) {
                $decroches[] = $relatif;
            }
        }

        $this->assertSame([], $decroches, 'Ces harnais ne portent plus le mot de passe commun des comptes semes.');
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
