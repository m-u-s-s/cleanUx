<?php

namespace Tests\Feature\Devops;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** LE PLAFOND MÉMOIRE DES TESTS NE DOIT PAS COMBATTRE CELUI DE LA CI. */
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

    /** LE TÉMOIN D'EXÉCUTION — la directive PREND-ELLE EFFET ? */
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
