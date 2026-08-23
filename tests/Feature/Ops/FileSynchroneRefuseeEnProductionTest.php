<?php

namespace Tests\Feature\Ops;

use App\Providers\AppServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** EN PRODUCTION, UNE FILE « sync » N'EST PAS UNE FILE (M-6, M-7). */
class FileSynchroneRefuseeEnProductionTest extends TestCase
{
    /** LA RÈGLE, MESURÉE TELLE QU'ELLE EST ÉCRITE. */
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
