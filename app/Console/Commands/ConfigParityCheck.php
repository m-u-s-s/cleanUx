<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Vérifie que l'environnement qui tourne correspond au profil de production. */
class ConfigParityCheck extends Command
{
    protected $signature = 'config:parity-check
                            {--json : Output machine-readable JSON instead of a table}';

    protected $description = 'Assert running config matches the production parity profile (exit 1 on mismatch).';

    /** Marqueur de gabarit. */
    private const GABARIT = 'CHANGE_ME';

    /**
     * Règles de parité avec la production.
     *
     * @var array<int, array{setting: string, allowed: list<string>, label: string, secret: bool}>
     */
    private array $rules = [
        // `app.env` EN TÊTE, PARCE QUE TROIS PROTECTIONS EN DÉPENDENT ET SE TAISENT.
        [
            'setting' => 'app.env',
            'allowed' => ['production'],
            'label' => 'production',
            'secret' => false,
        ],
        // APP_DEBUG expose la trace d'exécution, les requêtes SQL et les variables d'environnement sur la page d'erreur.
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

        // Secrets de paiement.
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

        // CHIFFREMENT DE LA SAUVEGARDE — la règle qui garde une étape du déploiement.
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

    /** Lit une clé de config et la normalise en chaîne. */
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
