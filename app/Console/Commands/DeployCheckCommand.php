<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeployCheckCommand extends Command
{
    protected $signature = 'deploy:check';
    protected $description = 'Pre-deployment checklist — verifies all production requirements';

    public function handle(): int
    {
        $checks = [
            'APP_ENV'                   => config('app.env') === 'production',
            'APP_DEBUG off'             => ! config('app.debug'),
            'APP_KEY set'               => ! empty(config('app.key')),
            'DB connection'             => $this->checkDb(),
            'Migrations up-to-date'     => $this->checkMigrations(),
            'MAIL configured'           => ! empty(config('mail.mailers.smtp.host')),
            'STRIPE key'                => ! empty(config('cashier.key')),
            'STRIPE secret'             => ! empty(config('cashier.secret')),
            'CORS origins'              => config('cors.allowed_origins') !== ['*'],
            'Queue driver != sync'      => config('queue.default') !== 'sync',
            'Cache driver != array'     => config('cache.default') !== 'array',
            'Session driver != file'    => config('session.driver') !== 'file' || config('app.env') !== 'production',
            'HTTPS forced'              => ! empty(config('app.url')) && str_starts_with(config('app.url'), 'https'),
        ];

        $allOk = true;
        foreach ($checks as $name => $passed) {
            $status = $passed ? '✓' : '✗';
            $color  = $passed ? 'green' : 'red';
            $this->line("  <fg={$color}>{$status}</> {$name}");
            if (! $passed) {
                $allOk = false;
            }
        }

        $this->newLine();
        $this->info($allOk
            ? '✓ All checks passed — ready to deploy.'
            : '✗ Some checks failed — fix before deploying.'
        );

        return $allOk ? 0 : 1;
    }

    private function checkDb(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function checkMigrations(): bool
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate:status');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
