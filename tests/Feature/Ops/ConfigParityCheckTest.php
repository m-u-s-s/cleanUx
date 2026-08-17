<?php

namespace Tests\Feature\Ops;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * `config:parity-check` — le contrôle qui doit refuser une configuration de prod incomplète.
 *
 * DEUX CHOSES SONT MESURÉES ICI.
 *
 * 1. LE COMPORTEMENT DE LA COMMANDE. On règle la config, on lance vraiment la commande, on lit
 *    son code de sortie et sa sortie.
 *
 * 2. SON CÂBLAGE DANS LE DÉPLOIEMENT. Une commande que personne n'appelle ne protège personne :
 *    jusqu'au 2026-08-12, `config:parity-check` n'était invoquée par AUCUN flux. Ces tests-là
 *    lisent les fichiers YAML — c'est la seule mesure possible depuis PHPUnit — et prennent deux
 *    précautions pour ne pas mesurer du vide : les lignes de commentaire sont retirées avant la
 *    recherche (un commentaire ne doit jamais suffire à faire passer le test), et l'absence d'un
 *    jalon fait échouer explicitement au lieu de comparer des positions fantômes.
 *
 * PIÈGE ÉVITÉ DANS CE FICHIER. Les cas d'échec partent tous d'un profil VALIDE et ne cassent
 * qu'un seul réglage. Sans cela, l'ajout des secrets Stripe les aurait laissés verts pour une
 * mauvaise raison : le code 1 aurait été garanti par des secrets vides, plus par la cause que le
 * test prétend mesurer. Chacun vérifie donc aussi que le réglage fautif est bien celui NOMMÉ dans
 * la liste des offenseurs.
 */
class ConfigParityCheckTest extends TestCase
{
    /** Un profil de production complet : tout est conforme, rien ne doit échouer. */
    private function profilDeProductionValide(): void
    {
        // Trois protections — HSTS, CSP, redirection HTTPS — ne s'arment qu'en environnement
        // « production ». Un hôte de prod laissé à `staging` les perd toutes les trois en silence.
        Config::set('app.env', 'production');
        Config::set('app.debug', false);
        Config::set('database.default', 'mysql');
        Config::set('queue.default', 'redis');
        Config::set('cache.default', 'redis');
        Config::set('broadcasting.default', 'reverb');
        Config::set('session.driver', 'database');
        Config::set('services.stripe.key', 'pk_live_valeur_de_test');
        Config::set('services.stripe.secret', 'sk_live_valeur_de_test');
        Config::set('services.stripe.webhook_secret', 'whsec_valeur_de_test');
        Config::set('services.stripe.connect_webhook_secret', 'whsec_connect_valeur_de_test');
        // Sans ce mot de passe, la sauvegarde que le déploiement prend juste avant de migrer est
        // écrite en clair sur le serveur applicatif.
        Config::set('backup.backup.password', 'mot_de_passe_archive_de_test');
    }

    public function test_passes_for_a_production_profile(): void
    {
        $this->profilDeProductionValide();

        $this->artisan('config:parity-check')->assertExitCode(0);
    }

    public function test_fails_when_queue_is_sync(): void
    {
        $this->profilDeProductionValide();
        Config::set('queue.default', 'sync'); // prod-unsafe — SEUL réglage cassé

        $this->artisan('config:parity-check')
            // « • queue.default » n'apparaît que dans la liste des offenseurs : la preuve que
            // l'échec vient bien de la file d'attente, et pas d'un autre réglage.
            ->expectsOutputToContain('• queue.default')
            ->assertExitCode(1);
    }

    public function test_fails_when_cache_is_file(): void
    {
        $this->profilDeProductionValide();
        Config::set('cache.default', 'file'); // prod-unsafe — SEUL réglage cassé

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• cache.default')
            ->assertExitCode(1);
    }

    public function test_names_the_offending_setting(): void
    {
        $this->profilDeProductionValide();
        Config::set('queue.default', 'sync');

        $this->artisan('config:parity-check')->expectsOutputToContain('queue')->assertExitCode(1);
    }

    // ── Environnement : les protections qui ne s'arment qu'en production ───────────────────

    /**
     * UN HÔTE DE PRODUCTION LAISSÉ À `staging` PERD TROIS PROTECTIONS SANS RIEN DIRE.
     *
     * HSTS (SecurityHeaders), la CSP de repli et la redirection HTTPS sont toutes conditionnées à
     * `app()->environment('production')`. Les pages s'affichent normalement, les tests passent,
     * et rien n'indique que le site est servi sans en-tête de transport strict.
     */
    public function test_echoue_quand_l_environnement_n_est_pas_production(): void
    {
        $this->profilDeProductionValide();
        Config::set('app.env', 'staging');

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• app.env')
            ->assertExitCode(1);
    }

    public function test_echoue_quand_le_mode_debogage_est_actif(): void
    {
        $this->profilDeProductionValide();
        Config::set('app.debug', true);

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• app.debug')
            ->assertExitCode(1);
    }

    /**
     * SANS CE MOT DE PASSE, CHAQUE DÉPLOIEMENT DÉPOSE LA BASE EN CLAIR.
     *
     * Le script lance `backup:run --only-db` juste avant de migrer. L'archive contient alors des
     * données personnelles soumises au RGPD, des empreintes de mots de passe et des jetons d'API,
     * conservées plusieurs jours sur le serveur applicatif.
     */
    public function test_echoue_quand_la_sauvegarde_n_est_pas_chiffree(): void
    {
        $this->profilDeProductionValide();
        Config::set('backup.backup.password', null);

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• backup.backup.password')
            ->assertExitCode(1);
    }

    // ── Secrets de paiement ────────────────────────────────────────────────────────────────

    public function test_echoue_quand_la_cle_publique_stripe_est_absente(): void
    {
        $this->profilDeProductionValide();
        Config::set('services.stripe.key', null);

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• services.stripe.key')
            ->assertExitCode(1);
    }

    public function test_echoue_quand_le_secret_stripe_est_absent(): void
    {
        $this->profilDeProductionValide();
        Config::set('services.stripe.secret', '');

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• services.stripe.secret')
            ->assertExitCode(1);
    }

    public function test_echoue_quand_le_secret_de_webhook_stripe_est_absent(): void
    {
        $this->profilDeProductionValide();
        Config::set('services.stripe.webhook_secret', null);

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• services.stripe.webhook_secret')
            ->assertExitCode(1);
    }

    /**
     * Le trou de H5 : sans ce secret, le contrôleur de webhook Connect ne peut vérifier aucune
     * signature. L'application démarre quand même — la panne n'apparaît qu'au premier paiement.
     */
    public function test_echoue_quand_le_secret_de_webhook_connect_est_absent(): void
    {
        $this->profilDeProductionValide();
        Config::set('services.stripe.connect_webhook_secret', null);

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• services.stripe.connect_webhook_secret')
            ->assertExitCode(1);
    }

    /**
     * `.env.production.example` livre `STRIPE_SECRET=sk_live_CHANGE_ME`. Copier le gabarit sans
     * le remplir ne doit pas suffire à passer le contrôle : la valeur est présente mais fausse.
     */
    public function test_echoue_quand_un_secret_est_reste_au_gabarit(): void
    {
        $this->profilDeProductionValide();
        Config::set('services.stripe.secret', 'sk_live_CHANGE_ME');

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• services.stripe.secret')
            ->assertExitCode(1);
    }

    /**
     * La sortie part dans des journaux de CI et de déploiement conservés longtemps : la valeur
     * d'un secret ne doit jamais y figurer, même quand tout va bien.
     */
    public function test_la_valeur_d_un_secret_n_est_jamais_ecrite_dans_la_sortie(): void
    {
        $this->profilDeProductionValide();
        Config::set('services.stripe.secret', 'sk_live_ce_texte_ne_doit_pas_fuiter');

        $this->artisan('config:parity-check')
            ->doesntExpectOutputToContain('sk_live_ce_texte_ne_doit_pas_fuiter')
            ->assertExitCode(0);
    }

    // ── Câblage dans les flux de déploiement ──────────────────────────────────────────────

    /** @return list<string> */
    private function fluxDeDeploiement(): array
    {
        return [
            '.github/workflows/deploy.yml',
            '.github/workflows/deploy-staging.yml',
        ];
    }

    /**
     * Le contenu du flux PRIVÉ DE SES COMMENTAIRES.
     *
     * Sans ce filtrage, une phrase de commentaire citant une commande suffirait à faire passer
     * les tests d'ordre — ils mesureraient de la prose au lieu de mesurer le script exécuté.
     */
    private function contenuExecutable(string $chemin): string
    {
        $absolu = base_path($chemin);

        $this->assertFileExists($absolu, "Le flux {$chemin} a disparu : ce test ne mesure plus rien.");

        $lignes = preg_split('/\R/', (string) file_get_contents($absolu)) ?: [];

        $lignes = array_filter($lignes, fn (string $ligne): bool => ! str_starts_with(ltrim($ligne), '#'));

        return implode("\n", $lignes);
    }

    /** Position d'un jalon, avec échec explicite s'il a disparu du script. */
    private function positionDe(string $contenu, string $jalon, string $chemin): int
    {
        $position = strpos($contenu, $jalon);

        $this->assertNotFalse($position, "{$chemin} : « {$jalon} » est absent du script de déploiement.");

        return $position;
    }

    /**
     * B3 — l'inversion qui migrait la base pendant que le job était rapporté en échec.
     *
     * `set -e` arrêtait le script à `config:cache`, qui échouait à chaque fois… mais APRÈS
     * `migrate --force`. La base avait donc déjà été mutée, migrations destructives comprises,
     * et sans sauvegarde. Tout ce qui peut échouer sans toucher aux données passe désormais avant.
     */
    public function test_les_flux_de_deploiement_valident_tout_avant_de_muter_la_base(): void
    {
        foreach ($this->fluxDeDeploiement() as $chemin) {
            $contenu = $this->contenuExecutable($chemin);
            $migration = $this->positionDe($contenu, 'php artisan migrate --force', $chemin);

            $prealables = [
                'php artisan config:cache',
                'php artisan route:cache',
                'php artisan view:cache',
                'php artisan event:cache',
                'php artisan config:parity-check',
                'php artisan ops:check-providers --strict',
                'php artisan storage:link',
                'php artisan backup:run',
            ];

            foreach ($prealables as $prealable) {
                $this->assertLessThan(
                    $migration,
                    $this->positionDe($contenu, $prealable, $chemin),
                    "{$chemin} : « {$prealable} » doit s'exécuter AVANT « migrate --force ». ".
                    'Sinon un échec de cette étape laisse une base déjà migrée derrière un job rouge.',
                );
            }
        }
    }

    /** Une commande que personne n'appelle ne protège personne (low 53). */
    public function test_les_flux_de_deploiement_appellent_le_controle_de_parite(): void
    {
        foreach ($this->fluxDeDeploiement() as $chemin) {
            $this->assertStringContainsString(
                'php artisan config:parity-check',
                $this->contenuExecutable($chemin),
                "{$chemin} : le contrôle de parité n'est pas câblé dans le déploiement.",
            );
        }
    }

    /**
     * H12 — la commande annonçait « bloque le déploiement » et n'y était pas câblée.
     *
     * Elle était planifiée toutes les trente minutes : elle CONSTATAIT donc le problème une
     * demi-heure après la mise en ligne, sur une plateforme déjà en train de tourner sur des
     * bouchons. Un bouchon réussit silencieusement — les SMS partent dans le vide, les
     * notifications aussi — et c'est précisément ce que le contrôle de parité ne voit pas :
     * le conteneur peut résoudre vers un Mock alors que toutes les variables sont présentes.
     */
    public function test_les_flux_de_deploiement_refusent_les_fournisseurs_bouchonnes(): void
    {
        foreach ($this->fluxDeDeploiement() as $chemin) {
            $this->assertStringContainsString(
                'php artisan ops:check-providers --strict',
                $this->contenuExecutable($chemin),
                "{$chemin} : rien n'empêche un déploiement de partir sur des fournisseurs bouchonnés.",
            );
        }
    }

    /** M-15 — le lien public/storage n'était créé nulle part ; les médias publics tombaient en 404. */
    public function test_les_flux_de_deploiement_relient_le_stockage_public(): void
    {
        foreach ($this->fluxDeDeploiement() as $chemin) {
            $this->assertStringContainsString(
                'php artisan storage:link',
                $this->contenuExecutable($chemin),
                "{$chemin} : `storage:link` manque — le lien public/storage n'est jamais recréé.",
            );
        }
    }

    /**
     * APP_KEY (low 54) — deux exigences opposées dans le même test, exprès.
     *
     * Le script doit ABANDONNER si la clé manque : sans elle, `config:cache` échoue et les
     * données chiffrées sont illisibles. Et il ne doit JAMAIS lancer `key:generate` : ce script
     * tourne à chaque déploiement, une clé regénérée rendrait définitivement indéchiffrable tout
     * ce qui l'a été avec l'ancienne (sessions, cookies, colonnes chiffrées).
     */
    public function test_les_flux_de_deploiement_gardent_la_cle_sans_jamais_la_regenerer(): void
    {
        foreach ($this->fluxDeDeploiement() as $chemin) {
            $contenu = $this->contenuExecutable($chemin);

            $this->assertStringNotContainsString(
                'key:generate',
                $contenu,
                "{$chemin} : `key:generate` en déploiement récurrent rendrait illisibles les données déjà chiffrées.",
            );

            $garde = $this->positionDe($contenu, 'ABANDON : APP_KEY', $chemin);
            $recuperation = $this->positionDe($contenu, 'git pull', $chemin);

            $this->assertLessThan(
                $recuperation,
                $garde,
                "{$chemin} : le garde APP_KEY doit passer en tête, avant même de récupérer le code.",
            );
        }
    }

    /**
     * L'ORDRE NE SUFFIT PAS : IL FAUT QUE L'ÉCHEC ARRÊTE LE SCRIPT.
     *
     * Les tests d'ordre ci-dessus comparent des positions. Ils restent donc verts si l'on écrit
     * `php artisan config:parity-check || true` : la sous-chaîne est intacte, la position aussi,
     * et pourtant la garantie a disparu — le script continuerait jusqu'à `migrate` malgré une
     * configuration de production incomplète. Même chose si quelqu'un retire `set -e` : chaque
     * étape échouerait dans le vide et le déploiement se déclarerait réussi.
     *
     * Ce test lit LIGNE PAR LIGNE, parce que c'est la ligne qui porte le neutralisant.
     */
    public function test_aucune_etape_bloquante_ne_peut_echouer_en_silence(): void
    {
        $etapesBloquantes = [
            'config:cache',
            'route:cache',
            'view:cache',
            'event:cache',
            'config:parity-check',
            'storage:link',
            'backup:run',
            'migrate --force',
        ];

        // `|| :` est l'écriture courte de `|| true` ; `continue-on-error` est son équivalent GitHub.
        $neutralisants = ['|| true', '||true', '|| :', 'continue-on-error: true', 'set +e'];

        foreach ($this->fluxDeDeploiement() as $chemin) {
            $lignes = preg_split('/\R/', $this->contenuExecutable($chemin)) ?: [];

            $this->assertTrue(
                (bool) preg_grep('/^\s*set -e/', $lignes),
                "{$chemin} : `set -e` est absent. Sans lui, chaque étape peut échouer sans arrêter ".
                'le déploiement, et tout l’ordre soigneusement établi ne garantit plus rien.',
            );

            foreach ($lignes as $numero => $ligne) {
                foreach ($etapesBloquantes as $etape) {
                    if (! str_contains($ligne, $etape)) {
                        continue;
                    }

                    foreach ($neutralisants as $neutralisant) {
                        $this->assertStringNotContainsString(
                            $neutralisant,
                            $ligne,
                            sprintf(
                                "%s ligne %d : « %s » neutralise l'échec de « %s ».\n".
                                'Une étape bloquante qui ne bloque plus laisse passer exactement ce '.
                                "qu'elle était censée arrêter.",
                                $chemin,
                                $numero + 1,
                                $neutralisant,
                                $etape,
                            ),
                        );
                    }
                }
            }
        }
    }

    /**
     * LA GARDE DE CHEMIN DOIT TESTER LA CHAÎNE VIDE, PAS SE FIER À `cd`.
     *
     * Une version de ce script s'appuyait sur `cd "$CHEMIN"` en supposant qu'un chemin vide ferait
     * échouer la commande sous `set -e`. C'est faux : `cd ""` rend 0 en bash et ne bouge pas. Un
     * secret DEPLOY_PATH absent — ce que GitHub rend par une chaîne vide, sans avertir — aurait
     * donc déroulé tout le déploiement dans le répertoire personnel du compte SSH.
     */
    public function test_les_flux_de_deploiement_refusent_un_chemin_de_deploiement_vide(): void
    {
        foreach ($this->fluxDeDeploiement() as $chemin) {
            $contenu = $this->contenuExecutable($chemin);

            $this->assertMatchesRegularExpression(
                '/(-z\s+"\$|test\s+-z|\[\[?\s+-z)/',
                $contenu,
                "{$chemin} : aucun test de chaîne vide sur le chemin de déploiement. ".
                '`cd ""` réussit et ne garde donc rien.',
            );
        }
    }
}
