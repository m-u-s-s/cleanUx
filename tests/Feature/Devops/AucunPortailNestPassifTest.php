<?php

namespace Tests\Feature\Devops;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * UN PORTAIL QUI NE BLOQUE RIEN N'EST PAS UN PORTAIL.
 *
 * `continue-on-error: true` fait tourner un job et jette son verdict. Le travail est payé, le
 * rouge s'affiche quelque part, et rien ne s'arrête. C'est raisonnable le temps de stabiliser une
 * vérification neuve — c'est exactement ce que l'E2E Playwright a fait — mais l'état transitoire
 * n'a aucune raison de se signaler quand il cesse de l'être. Il dure.
 *
 * CE DÉPÔT EN A DÉJÀ PAYÉ LE PRIX. La CI est restée structurellement rouge des mois sans que
 * personne le voie, et le jour où elle est repassée au vert elle a immédiatement révélé un
 * bloquant dur au déploiement, présent depuis tout ce temps. Un signal qu'on n'écoute plus ne
 * protège de rien, et un signal qu'on a soi-même rendu inaudible encore moins.
 *
 * CE TEST NE JUGE PAS DE LA PERTINENCE D'UN JOB — il exige seulement que la mise en sourdine soit
 * un GESTE VISIBLE. Rendre une vérification passive redevient une décision qu'on prend en
 * connaissance de cause, en touchant à ce fichier, plutôt qu'une ligne oubliée dans un coin.
 */
class AucunPortailNestPassifTest extends TestCase
{
    /**
     * Les jobs dont on ACCEPTE qu'ils ne bloquent pas, et pourquoi.
     *
     * Vide à dessein : les trois jobs bloquent. Ajouter une clé ici est le geste visible dont ce
     * fichier parle — il exige un motif écrit, daté, et la condition de retour.
     *
     * @var array<string, string>
     */
    private const PASSIFS_ASSUMES = [];

    public function test_aucun_job_de_ci_ne_jette_son_propre_verdict(): void
    {
        $workflow = Yaml::parse((string) file_get_contents(base_path('.github/workflows/ci.yml')));

        // Garde-fou du test : sans jobs lus, l'assertion serait vraie pour rien.
        $this->assertNotEmpty($workflow['jobs'] ?? [], 'Aucun job lu — le fichier a bougé ou ne parse plus.');

        foreach ($workflow['jobs'] as $nom => $job) {
            if (! ($job['continue-on-error'] ?? false)) {
                continue;
            }

            $this->assertArrayHasKey(
                $nom,
                self::PASSIFS_ASSUMES,
                "Le job « {$nom} » tourne sans que son verdict compte. S’il s’agit d’une "
                .'stabilisation volontaire, l’inscrire dans PASSIFS_ASSUMES avec son motif, sa date '
                .'et la condition qui le rendra bloquant — sans quoi l’état provisoire devient '
                .'définitif sans que personne le décide.',
            );
        }
    }

    /**
     * TÉMOIN — le test sait reconnaître un job passif.
     *
     * Sans lui, il passerait au vert sur un fichier qu'il ne sait pas lire, sur une clé qu'il
     * cherche au mauvais endroit, ou sur une boucle qui ne s'exécute jamais. C'est le contrôle
     * positif qu'exige tout test d'interdiction : prouver que le chemin fonctionne quand il doit
     * fonctionner, faute de quoi le vert mesure une panne.
     */
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
