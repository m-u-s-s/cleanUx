<?php

namespace Tests\Feature\Architecture;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** RELANCER `db:seed` NE DOIT RIEN DUPLIQUER. RIEN NE LE VÉRIFIAIT. */
class SeedersFonctionnentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tables observées pour détecter un doublon.
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
