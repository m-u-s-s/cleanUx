<?php

namespace Tests\Feature\Architecture;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RELANCER `db:seed` NE DOIT RIEN DUPLIQUER. RIEN NE LE VÉRIFIAIT.
 *
 * PREMIÈRE VERSION DE CETTE GARDE : FAUSSE, ET CORRIGÉE ICI. Elle s'ouvrait sur « aucun test
 * n'exécutait les seeders, 41 classes, zéro couverture ». C'était inexact sur les deux points :
 * 99 fichiers de test appellent `seed()`, trois chargent le `DatabaseSeeder` complet
 * (`DatabaseSeederProfileTest`, `DatabaseSeederReadinessTest`, `PrepareFreshSeedCommandTest`), et
 * `DatabaseSeederReadinessTest` vérifie déjà que le seed produit des lignes.
 *
 * Un seeder qui PLANTE était donc déjà couvert, et un seeder qui ne produit RIEN l'est depuis que
 * les compteurs des espaces société ont rejoint `PlatformReadinessReport` — au bon endroit, celui
 * qui sert aussi l'écran d'administration et la commande go-live.
 *
 * IL RESTE EXACTEMENT UNE PROPRIÉTÉ QUE PERSONNE NE VÉRIFIAIT : l'idempotence.
 *
 * Elle ne peut pas se déduire d'un seul passage — une base vierge est incapable de montrer un
 * doublon. Il faut seeder deux fois, et c'est précisément ce qu'aucun test ne faisait. C'est
 * pourtant le régime courant : on relance `db:seed` sur une base déjà chargée après avoir ajouté un
 * seeder. Un seul `insert()` sans clé de recherche casse la propriété, et le symptôme est
 * silencieux — pas d'erreur, juste des lignes qui s'accumulent à chaque appel.
 *
 * La même vérification tourne en CI sur MySQL réel (`db:seed` appelé deux fois dans le job
 * money-integrity), parce que la suite tourne sur SQLite : clés étrangères réellement appliquées,
 * mode strict et limite de 64 caractères sur les identifiants ne se voient que là-bas.
 */
class SeedersFonctionnentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tables observées pour détecter un doublon.
     *
     * On y met celles que les seeders remplissent le plus, espaces société compris : c'est là que
     * se logent les insertions non gardées, pas dans les tables de référence écrites une fois.
     *
     * @var list<string>
     */
    private const TABLES_SURVEILLEES = [
        'users',
        'organization_accounts',
        'organization_members',
        'organization_sites',
        'service_catalogs',
        'service_zones',
        'bookings',
        'field_teams',
        'field_team_members',
        'tasks',
        'channels',
        'channel_members',
        'messages',
        'missions',
        'mission_assignments',
    ];

    #[Test]
    public function relancer_le_seed_ne_duplique_rien(): void
    {
        $this->seed(DatabaseSeeder::class);

        $avant = $this->comptages();

        $this->seed(DatabaseSeeder::class);

        $apres = $this->comptages();

        $doubles = [];

        foreach ($avant as $table => $compte) {
            if ($apres[$table] !== $compte) {
                $doubles[] = sprintf('%s : %d → %d', $table, $compte, $apres[$table]);
            }
        }

        $this->assertSame(
            [],
            $doubles,
            'Le second passage du seed a fait varier ces comptages — un seeder insère sans clé de '
                ."recherche, et chaque `db:seed` empilera des doublons :\n  ".implode("\n  ", $doubles)
        );
    }

    /** @return array<string, int> */
    private function comptages(): array
    {
        $comptages = [];

        foreach (self::TABLES_SURVEILLEES as $table) {
            $comptages[$table] = DB::table($table)->count();
        }

        return $comptages;
    }
}
