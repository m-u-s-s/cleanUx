<?php

namespace Tests\Feature\Ops;

use App\Providers\AppServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * EN PRODUCTION, UNE FILE « sync » N'EST PAS UNE FILE (M-6, M-7).
 *
 * `config/queue.php` a pour défaut `sync`, et `.env.example` le reprend. Avec ce pilote, un job mis
 * en file s'exécute IMMÉDIATEMENT, dans le processus appelant — et le délai passé à `->delay()` est
 * purement ignoré.
 *
 * CE QUE ÇA CASSE, CONCRÈTEMENT. `EscalateMissionAssignmentJob` est dispatché avec
 * `->delay($assignment->expires_at)`. En `sync`, l'escalade part à l'instant même : le TTL de vingt
 * secondes et les vagues du moteur de répartition n'existent plus, et la première offre est écrasée
 * avant que le prestataire ait eu le temps de voir sa notification. Rien ne le signale : les
 * missions partent, les offres circulent, seul le rythme est faux.
 *
 * POURQUOI UN REFUS DE DÉMARRAGE. `config:parity-check` garde déjà le déploiement, mais quelqu'un
 * peut éditer `.env` sur le serveur sans redéployer. Un avertissement dans les journaux ne serait lu
 * par personne — c'est exactement ainsi que ce réglage a survécu jusqu'ici.
 *
 * ON N'INSTANCIE PAS UNE VRAIE APPLICATION DE PRODUCTION pour le mesurer : on appelle la garde
 * elle-même, en basculant l'environnement. Faire booter une application en `production` dans la
 * suite entraînerait la moitié des middlewares de sécurité et ne prouverait rien de plus.
 */
class FileSynchroneRefuseeEnProductionTest extends TestCase
{
    /**
     * LA RÈGLE, MESURÉE TELLE QU'ELLE EST ÉCRITE.
     *
     * `runningInConsole()` est figé au démarrage du conteneur et vaut TOUJOURS vrai sous PHPUnit :
     * un test qui prétendrait simuler une requête HTTP passerait pour une mauvaise raison. La
     * décision est donc isolée dans une méthode pure, dont les deux entrées sont explicites.
     */
    #[Test]
    public function seule_la_production_hors_console_sur_une_file_synchrone_est_refusee(): void
    {
        $refusee = fn (string $env, string $pilote, bool $console): bool => AppServiceProvider::laFileSynchroneEstRefusee($env, $pilote, $console);

        // LE SEUL CAS QUI DOIT MORDRE : production, sync, trafic réel.
        $this->assertTrue($refusee('production', 'sync', false));

        // La console reste ouverte, même sur une configuration fautive — sinon `config:clear`,
        // l'outil qui répare précisément ce défaut, deviendrait inaccessible.
        $this->assertFalse(
            $refusee('production', 'sync', true),
            'Une garde qui empêche de réparer ce qu’elle dénonce enferme l’exploitant dehors.'
        );

        // Une file réelle passe, quel que soit le pilote retenu.
        // Les sept combinaisons relevees ensemble : un refus trop large les casse toutes a la
        // fois, et une assertion par tour n'en nommerait qu'une.
        $refusesAtort = [];

        foreach (['redis', 'database', 'sqs', 'beanstalkd'] as $pilote) {
            if ($refusee('production', $pilote, false)) {
                $refusesAtort[] = "production + {$pilote}";
            }
        }

        // Hors production, `sync` est le bon reglage : aucun worker ne tourne sur un poste de dev.
        foreach (['local', 'testing', 'staging'] as $environnement) {
            if ($refusee($environnement, 'sync', false)) {
                $refusesAtort[] = "{$environnement} + sync";
            }
        }

        $this->assertSame([], $refusesAtort, 'Ces reglages sont legitimes et pourtant refuses.');
    }

    /** Le message doit nommer la cause ET la sortie, sinon il ne sert à personne à 3 h du matin. */
    #[Test]
    public function le_message_de_refus_dit_quoi_faire(): void
    {
        $this->assertStringContainsString('sync', AppServiceProvider::MESSAGE_FILE_SYNCHRONE);
        $this->assertStringContainsString('redis', AppServiceProvider::MESSAGE_FILE_SYNCHRONE);
    }
}
