<?php

namespace Tests\Feature\Devops;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * LE PLAFOND MÉMOIRE DES TESTS NE DOIT PAS COMBATTRE CELUI DE LA CI.
 *
 * `phpunit.xml` applique ses directives `<ini>` au DÉMARRAGE de PHPUnit — c'est-à-dire APRÈS le
 * `memory_limit` que le workflow pose dans `php.ini`. Une valeur finie ici annule donc silencieusement
 * celle de la CI, quelle qu'elle soit.
 *
 * CE QUE ÇA A COÛTÉ. Le 2026-08-14, deux poussées de suite sont sorties rouges. La suite passait
 * ENTIÈRE — 6391 tests, 20681 assertions — puis PHPUnit mourait en sérialisant le rapport de
 * couverture, 304 Mo demandés au-delà des 2 Go. Le seuil de couverture n'était jamais évalué,
 * `coverage.xml` n'était jamais produit, le déploiement restait `skipped`. Rien, dans ce rouge, ne
 * disait que le code allait bien — et c'est ainsi qu'on cesse de lire une CI.
 *
 * POURQUOI CE TEST PLUTÔT QU'UN NOMBRE PLUS GRAND. Parce que 2048M était déjà « confortable » le
 * jour où il a été écrit. Toute valeur fixe expire quand la suite grandit, et elle expire de la même
 * façon : un rouge qui n'a rien à voir avec le code. Ce test refuse le retour d'un plafond fini,
 * pas une valeur particulière.
 */
class LaMemoireDesTestsNestPasPlafonneeTest extends TestCase
{
    private const CONFIGURATION = __DIR__.'/../../../phpunit.xml';

    public function test_phpunit_ne_repose_aucun_plafond_memoire(): void
    {
        $xml = @simplexml_load_file(self::CONFIGURATION);

        $this->assertNotFalse($xml, 'phpunit.xml doit rester un XML valide.');

        $limites = $xml->xpath('//php/ini[@name="memory_limit"]') ?: [];

        // Garde-fou du test lui-même : une expression XPath qui ne capture plus rien rendrait
        // l'assertion suivante vraie pour une mauvaise raison.
        $this->assertCount(1, $limites, 'La directive doit exister et être unique.');

        $this->assertSame(
            '-1',
            (string) $limites[0]['value'],
            'Une valeur finie ici ANNULE le memory_limit de la CI : la couverture y meurt en '
            .'sérialisant son rapport, et le rouge ne parle plus du code.',
        );
    }

    /**
     * LE TÉMOIN D'EXÉCUTION — la directive PREND-ELLE EFFET ?
     *
     * Le test précédent lit un fichier ; celui-ci interroge le processus. Sans lui, une directive
     * mal placée — hors du bloc `<php>`, ou dans un fichier de configuration que PHPUnit n'ouvre
     * pas — passerait au vert en ne mesurant qu'une chaîne de caractères.
     */
    public function test_le_processus_de_test_tourne_bien_sans_plafond(): void
    {
        $this->assertSame(
            '-1',
            ini_get('memory_limit'),
            'phpunit.xml annonce -1 mais le processus tourne avec autre chose : la directive '
            .'n’est pas appliquée là où on le croit.',
        );
    }

    /**
     * L'AUTRE MOITIÉ : la CI doit continuer de poser sa propre valeur.
     *
     * Le jour où quelqu'un retirerait `ini-values` du workflow en pensant que `phpunit.xml` suffit,
     * les étapes qui ne passent PAS par PHPUnit — PHPStan, la construction des assets — retomberaient
     * sur le défaut de 512M. Les deux réglages se complètent, ils ne font pas double emploi.
     *
     * @return list<array{int}>
     */
    public static function jobsDuWorkflow(): array
    {
        return [[1], [2], [3]];
    }

    #[DataProvider('jobsDuWorkflow')]
    public function test_le_workflow_leve_la_limite_dans_chacun_de_ses_jobs(int $rang): void
    {
        $workflow = (string) file_get_contents(__DIR__.'/../../../.github/workflows/ci.yml');

        $occurrences = substr_count($workflow, 'memory_limit=-1');

        $this->assertGreaterThanOrEqual(
            $rang,
            $occurrences,
            'Chaque job PHP du workflow doit lever la limite : PHPStan et la construction des '
            .'assets ne passent pas par PHPUnit et retomberaient sur les 512M par défaut.',
        );
    }
}
