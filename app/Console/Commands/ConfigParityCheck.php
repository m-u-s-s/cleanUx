<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Vérifie que l'environnement qui tourne correspond au profil de production.
 *
 * Attrape les réglages dangereux en prod (queue=sync, cache=file…) ET les secrets
 * manquants avant qu'ils n'atteignent staging ou la prod. Appelée par les flux de
 * déploiement APRÈS `config:cache` (pour valider exactement ce que l'application lira)
 * et AVANT `migrate --force` (pour qu'une config incomplète n'entraîne aucune écriture
 * en base) :
 *   php artisan config:parity-check
 */
class ConfigParityCheck extends Command
{
    protected $signature = 'config:parity-check
                            {--json : Output machine-readable JSON instead of a table}';

    protected $description = 'Assert running config matches the production parity profile (exit 1 on mismatch).';

    /**
     * Marqueur de gabarit. `.env.production.example` livre des valeurs `CHANGE_ME…` :
     * une valeur qui en contient encore n'a jamais été renseignée, elle vaut donc « vide ».
     * Sans cela, copier le gabarit sans le remplir suffirait à faire passer le contrôle.
     */
    private const GABARIT = 'CHANGE_ME';

    /**
     * Règles de parité avec la production.
     *
     * Chaque entrée décrit une clé de config et l'ensemble des valeurs acceptées.
     *
     * - `allowed` non vide : la valeur doit appartenir à la liste blanche.
     * - `allowed` VIDE : toute valeur renseignée est acceptée (le contrôle porte alors sur
     *   la simple présence — c'est le cas des secrets, dont on ne peut pas énumérer les
     *   valeurs licites).
     * - `secret` : la valeur réelle n'est JAMAIS écrite dans la sortie. Ces commandes
     *   tournent dans des journaux de CI et de déploiement conservés longtemps ; on n'y
     *   recopie pas une clé Stripe. On n'affiche que « défini / vide / gabarit ».
     *
     * @var array<int, array{setting: string, allowed: list<string>, label: string, secret: bool}>
     */
    private array $rules = [
        /*
         * `app.env` EN TÊTE, PARCE QUE TROIS PROTECTIONS EN DÉPENDENT ET SE TAISENT.
         *
         * HSTS, la CSP de production et la redirection HTTPS sont toutes conditionnées à
         * `app()->environment('production')`. Un hôte de production laissé à APP_ENV=staging sert
         * donc sans HSTS, sans CSP et sans redirection — et rien ne le signale : les pages
         * s'affichent normalement. C'est le genre de défaut qu'on ne découvre qu'après.
         */
        [
            'setting' => 'app.env',
            'allowed' => ['production'],
            'label' => 'production',
            'secret' => false,
        ],
        /*
         * APP_DEBUG expose la trace d'exécution, les requêtes SQL et les variables d'environnement
         * sur la page d'erreur. La valeur lue est un booléen ; on la compare à sa forme chaîne,
         * comme le reste des règles.
         */
        [
            'setting' => 'app.debug',
            'allowed' => ['', '0', 'false'],
            'label' => 'false',
            'secret' => false,
        ],
        [
            'setting' => 'database.default',
            'allowed' => ['mysql', 'pgsql', 'sqlsrv'],
            'label' => 'mysql | pgsql | sqlsrv',
            'secret' => false,
        ],
        [
            'setting' => 'queue.default',
            'allowed' => ['redis', 'sqs', 'database', 'beanstalkd'],
            'label' => 'redis | sqs | database  (not sync)',
            'secret' => false,
        ],
        [
            'setting' => 'cache.default',
            'allowed' => ['redis', 'memcached', 'dynamodb'],
            'label' => 'redis | memcached | dynamodb  (not file/array)',
            'secret' => false,
        ],
        [
            'setting' => 'broadcasting.default',
            'allowed' => ['reverb', 'pusher', 'ably'],
            'label' => 'reverb | pusher  (not null/log)',
            'secret' => false,
        ],
        [
            'setting' => 'session.driver',
            'allowed' => ['database', 'redis', 'cookie'],
            'label' => 'database | redis  (not file/array)',
            'secret' => false,
        ],

        /*
         * Secrets de paiement. Sans eux l'application démarre, sert des pages, et ne casse
         * qu'au moment où un client paie : la panne est différée jusqu'au premier euro.
         *
         * `connect_webhook_secret` est le trou constaté (H5) : le contrôleur de webhook
         * Connect vérifie la signature avec ce secret ; absent, chaque notification Stripe
         * part en erreur alors que rien, au déploiement, ne le signalait.
         */
        [
            'setting' => 'services.stripe.key',
            'allowed' => [],
            'label' => 'non vide  (STRIPE_KEY)',
            'secret' => true,
        ],
        [
            'setting' => 'services.stripe.secret',
            'allowed' => [],
            'label' => 'non vide  (STRIPE_SECRET)',
            'secret' => true,
        ],
        [
            'setting' => 'services.stripe.webhook_secret',
            'allowed' => [],
            'label' => 'non vide  (STRIPE_WEBHOOK_SECRET)',
            'secret' => true,
        ],
        [
            'setting' => 'services.stripe.connect_webhook_secret',
            'allowed' => [],
            'label' => 'non vide  (STRIPE_CONNECT_WEBHOOK_SECRET)',
            'secret' => true,
        ],

        /*
         * CHIFFREMENT DE LA SAUVEGARDE — la règle qui garde une étape du déploiement.
         *
         * Le script de déploiement lance `backup:run --only-db` juste avant la migration. Sans ce
         * mot de passe, spatie/laravel-backup écrit l'archive EN CLAIR : chaque déploiement dépose
         * alors sur le serveur applicatif une copie complète de la base — données personnelles
         * soumises au RGPD, empreintes de mots de passe, jetons d'API — conservée plusieurs jours.
         *
         * La règle est ici plutôt que dans le script parce qu'un contrôle qu'on peut oublier de
         * copier dans le prochain flux de déploiement n'est pas un contrôle.
         */
        [
            'setting' => 'backup.backup.password',
            'allowed' => [],
            'label' => 'non vide  (BACKUP_ARCHIVE_PASSWORD)',
            'secret' => true,
        ],
    ];

    public function handle(): int
    {
        $rows = [];
        $hasFail = false;

        foreach ($this->rules as $rule) {
            $actual = $this->valeurLue($rule['setting']);

            $ok = $rule['allowed'] === []
                ? $this->estRenseignee($actual)
                : in_array($actual, $rule['allowed'], true);

            if (! $ok) {
                $hasFail = true;
            }

            $rows[] = [
                'setting' => $rule['setting'],
                'expected' => $rule['label'],
                'actual' => $this->valeurAffichable($rule['secret'], $actual),
                'ok' => $ok ? '✓' : '✗',
            ];
        }

        // ── output ──────────────────────────────────────────────────────────
        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['Setting', 'Expected', 'Actual', 'OK'],
                array_map(fn ($r) => array_values($r), $rows),
            );
        }

        if ($hasFail) {
            $bad = array_filter($rows, fn ($r) => $r['ok'] === '✗');
            $this->error('Production parity check FAILED. Prod-unsafe settings:');
            foreach ($bad as $r) {
                // Each offending setting name is printed → tests can assert substring.
                $this->line("  • {$r['setting']}  (actual: {$r['actual']})");
            }

            return self::FAILURE;
        }

        $this->info('Environment matches production profile.');

        return self::SUCCESS;
    }

    /**
     * Lit une clé de config et la normalise en chaîne.
     *
     * Une clé absente, nulle ou non scalaire (un tableau de config mal ciblé) vaut « vide » :
     * on préfère un échec du contrôle à une exception de conversion.
     */
    private function valeurLue(string $setting): string
    {
        $brut = config($setting);

        return is_scalar($brut) ? trim((string) $brut) : '';
    }

    /** Une valeur vide, ou restée au gabarit `CHANGE_ME`, n'est pas renseignée. */
    private function estRenseignee(string $valeur): bool
    {
        if ($valeur === '') {
            return false;
        }

        return ! str_contains(strtoupper($valeur), self::GABARIT);
    }

    /** Ce qu'on accepte d'écrire dans un journal : jamais la valeur d'un secret. */
    private function valeurAffichable(bool $secret, string $valeur): string
    {
        if (! $secret) {
            return $valeur !== '' ? $valeur : '(empty)';
        }

        if ($valeur === '') {
            return '(empty)';
        }

        return $this->estRenseignee($valeur) ? '(défini)' : '(gabarit '.self::GABARIT.')';
    }
}
