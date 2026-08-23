<?php

namespace Tests\Feature\Devops;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/** UN PORTAIL QUI NE BLOQUE RIEN N'EST PAS UN PORTAIL. */
class AucunPortailNestPassifTest extends TestCase
{
    /**
     * Les jobs dont on ACCEPTE qu'ils ne bloquent pas, et pourquoi.
     *
     * @var array<string, string>
     */
    private const PASSIFS_ASSUMES = [];

    public function test_aucun_job_de_ci_ne_jette_son_propre_verdict(): void
    {
        $workflow = Yaml::parse((string) file_get_contents(base_path('.github/workflows/ci.yml')));

        // Garde-fou du test : sans jobs lus, l'assertion serait vraie pour rien.
        $this->assertNotEmpty($workflow['jobs'] ?? [], 'Aucun job lu — le fichier a bougé ou ne parse plus.');

        // TOUS les jobs devenus passifs d'un coup : desamorcer un portail donne envie d'en
        // desamorcer un second, et c'est la liste entiere qu'on veut voir avant de la tolerer.
        $passifs = [];

        foreach ($workflow['jobs'] as $nom => $job) {
            if (! ($job['continue-on-error'] ?? false)) {
                continue;
            }

            if (! array_key_exists($nom, self::PASSIFS_ASSUMES)) {
                $passifs[] = $nom;
            }
        }

        $this->assertSame(
            [],
            $passifs,
            'Ces jobs tournent sans que leur verdict compte. S il s agit d une stabilisation '
            .'volontaire, les inscrire dans PASSIFS_ASSUMES avec leur motif, leur date et la '
            .'condition qui les rendra bloquants — sans quoi l etat provisoire devient definitif '
            .'sans que personne le decide.',
        );
    }

    /** TÉMOIN — le test sait reconnaître un job passif. */
    public function test_temoin_un_job_passif_serait_bien_detecte(): void
    {
        $factice = Yaml::parse(<<<'YAML'
            jobs:
              bloquant:
                runs-on: ubuntu-latest
              passif:
                runs-on: ubuntu-latest
                continue-on-error: true
            YAML);

        $passifs = [];
        foreach ($factice['jobs'] as $nom => $job) {
            if ($job['continue-on-error'] ?? false) {
                $passifs[] = $nom;
            }
        }

        $this->assertSame(['passif'], $passifs);
    }
}
