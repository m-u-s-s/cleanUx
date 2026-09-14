<?php

namespace Tests\Feature\Devops;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TROIS RÉGLAGES QUE LA SUITE NE PEUT PAS EXERCER, et qui n'existent qu'en production : elle tourne
 * en `sync` + `array`, le développement en `database` + `file`, la production en `redis`.
 *
 * Faute de pouvoir jouer la course, on vérifie ici les INVARIANTS de configuration qui la rendent
 * impossible — et le seul comportement réellement observable, la purge ciblée des taux.
 */
class LaFileEtLeCacheNeSeVidentPasSeulsTest extends TestCase
{
    use RefreshDatabase;

    /** Le plus long `--timeout` déclaré par un worker de déploiement. */
    private const TIMEOUT_WORKER_LE_PLUS_LONG = 600;

    #[Test]
    public function retry_after_depasse_le_timeout_du_worker_le_plus_long(): void
    {
        foreach (['database', 'beanstalkd', 'redis'] as $connexion) {
            $retryAfter = (int) config("queue.connections.{$connexion}.retry_after");

            $this->assertGreaterThan(
                self::TIMEOUT_WORKER_LE_PLUS_LONG,
                $retryAfter,
                "queue.connections.{$connexion}.retry_after ({$retryAfter}s) doit dépasser le plus long ".
                '--timeout de worker, sinon un job long est rejoué EN PARALLÈLE de lui-même.',
            );
        }
    }

    /** Le témoin : le fichier de déploiement dit toujours ce que la borne ci-dessus suppose. */
    #[Test]
    public function le_timeout_suppose_est_bien_celui_des_workers_deployes(): void
    {
        $conf = (string) file_get_contents(base_path('deploy/supervisor/brio-worker.conf.example'));

        $this->assertStringContainsString('--timeout='.self::TIMEOUT_WORKER_LE_PLUS_LONG, $conf);
    }

    #[Test]
    public function un_job_enfile_dans_une_transaction_attend_le_commit(): void
    {
        foreach (['database', 'beanstalkd', 'sqs', 'redis'] as $connexion) {
            $this->assertTrue(
                (bool) config("queue.connections.{$connexion}.after_commit"),
                "queue.connections.{$connexion}.after_commit doit être vrai : sinon un worker ".
                'consomme le job avant le commit, et un rollback ne le retire pas.',
            );
        }
    }

    #[Test]
    public function le_rafraichissement_des_taux_ne_vide_pas_tout_le_cache(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response(['rates' => ['USD' => 1.1]], 200),
        ]);

        Cache::put('fx:rate:EUR:USD', 9.99, 3600);
        Cache::put('verrou-de-l-ordonnanceur', 'tenu', 3600);
        Cache::put('compteur-de-connexions', 42, 3600);

        $this->artisan('currencies:refresh')->assertExitCode(0);

        // Le taux réécrit est bien oublié…
        $this->assertNull(Cache::get('fx:rate:EUR:USD'));

        // …et rien d'autre n'a été emporté.
        $this->assertSame('tenu', Cache::get('verrou-de-l-ordonnanceur'));
        $this->assertSame(42, Cache::get('compteur-de-connexions'));
    }
}
