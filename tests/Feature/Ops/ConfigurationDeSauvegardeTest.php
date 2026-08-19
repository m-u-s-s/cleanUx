<?php

namespace Tests\Feature\Ops;

use PHPUnit\Framework\Attributes\Test;
use Spatie\Backup\Config\NotificationMailConfig;
use Tests\TestCase;

/**
 * LA LIGNE QUI FAISAIT ÉCHOUER TOUT DÉPLOIEMENT, DEPUIS TOUJOURS.
 *
 * `config/backup.php` lisait `env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS'))` —
 * l'`env()` INTÉRIEUR sans défaut. Sur un runner, `composer install` s'exécute avant qu'un `.env`
 * existe : la valeur vaut alors `null`, et `NotificationMailConfig::fromArray()` la refuse pendant
 * `package:discover`. Le job mourait à l'installation des dépendances, sans jamais atteindre
 * l'étape SSH.
 *
 * Le diagnostic accusait les secrets manquants. Ils n'étaient même pas lus.
 *
 * ── CE QUE CE TEST GARDE ─────────────────────────────────────────────────────────────────────
 *
 * Le fichier de configuration est relu À LA MAIN, avec l'environnement vidé de ses deux clés :
 * c'est la seule façon de reproduire le runner, où `config('backup')` est déjà résolu depuis le
 * `.env` de test. Et le témoin positif est en tête — sans lui, « ça ne lève pas » se mesurerait
 * aussi bien sur une configuration qui n'envoie jamais rien à personne.
 */
class ConfigurationDeSauvegardeTest extends TestCase
{
    /**
     * Relit `config/backup.php` avec l'environnement demandé, comme le ferait un démarrage neuf.
     *
     * @param  array<string, string|null>  $environnement
     * @return array<string, mixed>
     */
    private function relire(array $environnement): array
    {
        $anciennes = [];

        foreach ($environnement as $cle => $valeur) {
            $anciennes[$cle] = $_ENV[$cle] ?? null;

            if ($valeur === null) {
                unset($_ENV[$cle], $_SERVER[$cle]);
                putenv($cle);
            } else {
                $_ENV[$cle] = $valeur;
                $_SERVER[$cle] = $valeur;
                putenv("{$cle}={$valeur}");
            }
        }

        try {
            return require config_path('backup.php');
        } finally {
            foreach ($anciennes as $cle => $valeur) {
                if ($valeur === null) {
                    unset($_ENV[$cle], $_SERVER[$cle]);
                    putenv($cle);
                } else {
                    $_ENV[$cle] = $valeur;
                    $_SERVER[$cle] = $valeur;
                    putenv("{$cle}={$valeur}");
                }
            }
        }
    }

    /**
     * LE TÉMOIN POSITIF : une adresse configurée est bien celle qui reçoit.
     *
     * Sans lui, le test suivant passerait au vert sur une configuration qui n'envoie jamais rien
     * à personne — c'est-à-dire en mesurant une panne.
     */
    #[Test]
    public function une_adresse_configuree_recoit_bien_les_rapports(): void
    {
        $config = $this->relire([
            'BACKUP_NOTIFICATION_EMAIL' => 'ops@exemple.test',
            'MAIL_FROM_ADDRESS' => 'no-reply@exemple.test',
        ]);

        $this->assertSame('ops@exemple.test', $config['notifications']['mail']['to']);

        // Et spatie l'accepte : c'est le même appel qui s'exécute pendant `package:discover`.
        NotificationMailConfig::fromArray($config['notifications']['mail']);
    }

    /** À défaut d'adresse dédiée, celle d'expédition de l'application fait office. */
    #[Test]
    public function l_adresse_d_expedition_sert_de_repli(): void
    {
        $config = $this->relire([
            'BACKUP_NOTIFICATION_EMAIL' => null,
            'MAIL_FROM_ADDRESS' => 'no-reply@exemple.test',
        ]);

        $this->assertSame('no-reply@exemple.test', $config['notifications']['mail']['to']);
    }

    /**
     * LE DÉFAUT : aucune des deux clés, exactement l'état du runner avant qu'un `.env` existe.
     *
     * Le repli est un TABLEAU VIDE et non une adresse d'exemple : recopier `hello@example.com`
     * enverrait un rapport de sauvegarde — chemins de serveur et fragments de configuration
     * inclus — à un domaine qui appartient à quelqu'un d'autre.
     */
    #[Test]
    public function sans_aucune_adresse_la_decouverte_des_paquets_ne_leve_plus(): void
    {
        $config = $this->relire([
            'BACKUP_NOTIFICATION_EMAIL' => null,
            'MAIL_FROM_ADDRESS' => null,
        ]);

        $this->assertSame([], $config['notifications']['mail']['to'], 'pas d’adresse = pas de destinataire');

        // L'appel qui faisait mourir `composer install`. Il doit passer.
        $mail = NotificationMailConfig::fromArray($config['notifications']['mail']);

        $this->assertSame([], $mail->to);
    }
}
