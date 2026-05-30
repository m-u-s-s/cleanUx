<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Asserts that the running environment matches the production profile.
 *
 * Catches prod-unsafe defaults (queue=sync, cache=file, etc.) before
 * they slip into staging or prod. Run in CI / deploy pipelines:
 *   php artisan config:parity-check
 */
class ConfigParityCheck extends Command
{
    protected $signature = 'config:parity-check
                            {--json : Output machine-readable JSON instead of a table}';

    protected $description = 'Assert running config matches the production parity profile (exit 1 on mismatch).';

    /**
     * Production-parity rules.
     *
     * Each entry defines one config key and the set of allowed values.
     * An empty `allowed` array means "any non-null value is accepted".
     */
    private array $rules = [
        [
            'setting' => 'database.default',
            'allowed' => ['mysql', 'pgsql', 'sqlsrv'],
            'label' => 'mysql | pgsql | sqlsrv',
        ],
        [
            'setting' => 'queue.default',
            'allowed' => ['redis', 'sqs', 'database', 'beanstalkd'],
            'label' => 'redis | sqs | database  (not sync)',
        ],
        [
            'setting' => 'cache.default',
            'allowed' => ['redis', 'memcached', 'dynamodb'],
            'label' => 'redis | memcached | dynamodb  (not file/array)',
        ],
        [
            'setting' => 'broadcasting.default',
            'allowed' => ['reverb', 'pusher', 'ably'],
            'label' => 'reverb | pusher  (not null/log)',
        ],
        [
            'setting' => 'session.driver',
            'allowed' => ['database', 'redis', 'cookie'],
            'label' => 'database | redis  (not file/array)',
        ],
    ];

    public function handle(): int
    {
        $rows = [];
        $hasFail = false;

        foreach ($this->rules as $rule) {
            $actual = (string) config($rule['setting'], '');
            $ok = in_array($actual, $rule['allowed'], true);

            if (! $ok) {
                $hasFail = true;
            }

            $rows[] = [
                'setting' => $rule['setting'],
                'expected' => $rule['label'],
                'actual' => $actual ?: '(empty)',
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
}
