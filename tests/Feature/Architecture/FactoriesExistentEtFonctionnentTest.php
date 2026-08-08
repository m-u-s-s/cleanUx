<?php

namespace Tests\Feature\Architecture;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UNE FACTORY MANQUANTE OU PÉRIMÉE NE SE VOIT QU'AU MOMENT D'ÉCRIRE UN TEST.
 *
 * Ce dépôt compte 243 modèles pour 226 factories. Le manque ne gêne personne — jusqu'au jour où
 * quelqu'un veut tester le modèle concerné et découvre qu'il doit d'abord écrire la factory, en
 * devinant les colonnes obligatoires. C'est un coût déplacé, pas évité.
 *
 * Pire : une factory qui a dérivé du schéma reste verte tant qu'aucun test ne l'utilise. Le
 * sondage initial en a trouvé deux qui renseignent une colonne `type` absente de leur table.
 *
 * DEUX VOLETS, POUR DEUX COÛTS DIFFÉRENTS :
 *
 *   1. « chaque modèle a une factory » — statique, instantané ;
 *   2. « chaque factory produit un modèle » — instancie réellement, donc plus lent, mais c'est le
 *      seul moyen de voir une factory qui ment sur le schéma.
 *
 * PIÈGE DE MESURE, appris en construisant cette garde : instancier des centaines de factories dans
 * un même processus épuise le registre d'unicité de Faker. Il répond alors « Maximum retries of
 * 10000 reached » pour TOUTES les suivantes — une cascade de faux échecs qui n'a rien à voir avec
 * les factories elles-mêmes. D'où le `fake()->unique(true)` avant chacune.
 */
class FactoriesExistentEtFonctionnentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * MODÈLES SANS FACTORY, DÉLIBÉRÉMENT.
     *
     * Tout modèle recensé ici doit l'être AVEC SA RAISON. Un oubli n'y a pas sa place : la liste
     * sert à dire « ce cas est réglé », pas « on verra plus tard ».
     *
     * @var list<string>
     */
    private const SANS_FACTORY_ASSUME = [
        /*
         * `PersonalAccessTokenV2` étend le modèle de Sanctum. Un jeton ne se fabrique pas : il naît
         * de `createToken()`, qui génère la valeur en clair ET son hachage, et ne rend la première
         * qu'une seule fois. Une factory produirait une ligne cohérente en apparence mais dont
         * aucun jeton utilisable ne sortirait — pire qu'une absence de factory.
         */
        'PersonalAccessTokenV2',
    ];

    #[Test]
    public function chaque_modele_possede_une_factory(): void
    {
        $manquants = [];
        $verifies = 0;

        foreach ($this->modeles() as $classe) {
            $court = class_basename($classe);

            if (in_array($court, self::SANS_FACTORY_ASSUME, true)) {
                continue;
            }

            $verifies++;

            /*
             * ON INTERROGE LE MODÈLE, PAS LE DISQUE.
             *
             * Chercher `factories/<Nom>Factory.php` à plat déclarait manquantes les factories des
             * modèles en sous-espace de noms — `SubscriptionsV2` — alors qu'elles existent, rangées
             * dans le sous-dossier miroir que Laravel attend. Le trait `HasFactory` et la
             * résolution de Laravel font autorité ; le chemin de fichier n'est qu'une convention.
             */
            if (! in_array(HasFactory::class, class_uses_recursive($classe), true)) {
                $manquants[] = $court.' (sans HasFactory)';

                continue;
            }

            try {
                $classe::factory();
            } catch (\Throwable) {
                $manquants[] = $court;
            }
        }

        $this->assertGreaterThan(
            150,
            $verifies,
            'La garde ne trouve presque aucun modèle : son parcours de fichiers ne mord plus.'
        );

        $this->assertSame(
            [],
            $manquants,
            "Ces modèles n'ont pas de factory — écrire un test les concernant obligera à la deviner :\n  "
                .implode("\n  ", $manquants)
        );
    }

    #[Test]
    public function chaque_factory_produit_un_modele(): void
    {
        $casses = [];
        $verifies = 0;

        /*
         * Récursif ici aussi : les factories d'abonnement V2 vivent dans un sous-dossier miroir de
         * leur modèle, parce que Laravel résout `App\Models\X\Y` vers `Database\Factories\X\YFactory`.
         * Une glob à plat les manquerait et la garde se tairait sur elles.
         */
        foreach ($this->fichiersFactory() as $fichier) {
            $court = str_replace('Factory', '', basename($fichier, '.php'));

            /*
             * ON DEMANDE À LA FACTORY QUEL MODÈLE ELLE SERT, PLUTÔT QUE DE LE DEVINER.
             *
             * Ma première version supposait `App\Models\<Nom>` et déclarait « la factory ne
             * correspond à aucun modèle » pour les quatre factories d'abonnement V2, dont les
             * modèles vivent dans `App\Models\SubscriptionsV2\`. Quatre faux positifs produits par
             * la garde elle-même : c'est `modelName()` qui fait autorité.
             */
            $relatifFactory = str_replace(
                [database_path('factories').DIRECTORY_SEPARATOR, '.php'],
                '',
                $fichier
            );

            $classeFactory = 'Database\\Factories\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relatifFactory);

            if (! class_exists($classeFactory)) {
                $casses[] = "{$court} : classe de factory introuvable";

                continue;
            }

            $classe = (new $classeFactory)->modelName();

            if (! class_exists($classe) || ! is_subclass_of($classe, Model::class)) {
                $casses[] = "{$court} : la factory ne correspond à aucun modèle";

                continue;
            }

            if (! in_array(HasFactory::class, class_uses_recursive($classe), true)) {
                $casses[] = "{$court} : le modèle n'utilise pas HasFactory, sa factory est inatteignable";

                continue;
            }

            $verifies++;

            try {
                // Sans cette remise à zéro, Faker s'épuise au bout de quelques dizaines d'appels et
                // fait échouer TOUTES les factories suivantes pour une raison qui n'est pas la leur.
                fake()->unique(true);

                /*
                 * CHAQUE FACTORY EST ÉPROUVÉE SUR UNE BASE VIERGE DE SES VOISINES.
                 *
                 * `make()` n'écrit pas le modèle lui-même, mais il CRÉE ses parents (un
                 * `belongsTo` déclaré en factory insère réellement). Sans transaction annulée, ces
                 * parents s'accumulent : la vingtième factory à créer un utilisateur heurte une
                 * contrainte d'unicité sur l'e-mail, et l'on croit à un défaut de la factory alors
                 * que c'est l'accumulation qui parle.
                 *
                 * Cinq des sept échecs de ma première version étaient exactement cela.
                 */
                DB::beginTransaction();

                try {
                    $classe::factory()->make();
                } finally {
                    DB::rollBack();
                }
            } catch (\Throwable $e) {
                $casses[] = sprintf(
                    '%s : %s',
                    $court,
                    substr(str_replace("\n", ' ', $e->getMessage()), 0, 120)
                );
            }
        }

        $this->assertGreaterThan(
            150,
            $verifies,
            'La garde ne trouve presque aucune factory : son parcours de fichiers ne mord plus.'
        );

        $this->assertSame(
            [],
            $casses,
            "Ces factories ne produisent plus de modèle valide :\n  ".implode("\n  ", $casses)
        );
    }

    /** @return list<string> chemins de tous les fichiers de factory, sous-dossiers compris */
    private function fichiersFactory(): array
    {
        $fichiers = [];

        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(database_path('factories'))
        );

        foreach ($iterateur as $entree) {
            if ($entree->isFile() && str_ends_with($entree->getFilename(), 'Factory.php')) {
                $fichiers[] = $entree->getPathname();
            }
        }

        sort($fichiers);

        return $fichiers;
    }

    /** @return list<class-string<Model>> */
    private function modeles(): array
    {
        $classes = [];

        /*
         * PARCOURS RÉCURSIF. `app/Models` contient des sous-espaces de noms — `SubscriptionsV2`,
         * `Contracts`, `Sanctum`, `Concerns`. Ma première version ne lisait que la racine et
         * ignorait donc silencieusement tout ce qui s'y trouve : une garde qui ne regarde pas
         * partout donne une assurance qu'elle n'a pas.
         */
        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Models'))
        );

        foreach ($iterateur as $entree) {
            if (! $entree->isFile() || $entree->getExtension() !== 'php') {
                continue;
            }

            $relatif = str_replace(
                [app_path('Models').DIRECTORY_SEPARATOR, '.php'],
                '',
                $entree->getPathname()
            );

            $classe = 'App\\Models\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relatif);

            if (class_exists($classe) && is_subclass_of($classe, Model::class)) {
                $classes[] = $classe;
            }
        }

        return $classes;
    }
}
