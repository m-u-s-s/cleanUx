<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Pré-vol go-live — exécute les contrôles AUTOMATISABLES des drills D1–D6 et rend
 * un go/no-go consolidé, avant les drills manuels (D2/D3/D4) que l'opérateur joue
 * contre staging + Stripe test-mode. Gate sur parité + missions bloquées.
 */
class GoLivePreflightCommand extends Command
{
    protected $signature = 'golive:preflight {--with-restore : Inclure aussi le restore-drill (connexion drill)}';

    protected $description = 'Pré-vol go-live : lance les contrôles automatisables (D1 parité+santé, D5 stuck-missions) et résume le go/no-go.';

    public function handle(): int
    {
        $this->info('=== Pré-vol go-live (drills automatisables) ===');

        $results = [];

        // D1 — parité de config production (GATE).
        $parityExit = Artisan::call('config:parity-check');
        $results[] = ['D1', 'config:parity-check', $parityExit === 0, true];

        // D1 — santé du spine (INFORMATIF : la commande sort toujours 0).
        Artisan::call('spine:health-report');
        $healthOk = str_contains(Artisan::output(), ', 0 failing');
        $results[] = ['D1', 'spine:health-report', $healthOk, false];

        // D5 — missions bloquées (GATE).
        $stuckExit = Artisan::call('spine:check-stuck-missions');
        $results[] = ['D5', 'spine:check-stuck-missions', $stuckExit === 0, true];

        // D6 — restore drill (optionnel ; GATE si lancé).
        if ($this->option('with-restore')) {
            $scratch = (string) config('backup.restore.scratch_connection', 'scratch');
            $restoreExit = Artisan::call('backup:restore-drill', ['--connection' => $scratch]);
            $results[] = ['D6', 'backup:restore-drill', $restoreExit === 0, true];
        }

        $this->newLine();
        $this->table(
            ['Drill', 'Contrôle', 'Résultat', 'Gate'],
            array_map(fn ($r) => [$r[0], $r[1], $r[2] ? 'PASS' : 'FAIL', $r[3] ? 'oui' : 'info'], $results),
        );

        $gateFailures = array_filter($results, fn ($r) => $r[3] && ! $r[2]);

        $this->newLine();
        $this->warn('Drills MANUELS restants (opérateur, staging + Stripe test-mode) :');
        $this->line('  • D2 — Money spine (PaymentIntent → capture → transfer → réconciliation)');
        $this->line('  • D3 — Webhooks (idempotence + retry)');
        $this->line('  • D4 — Refund / Cancel / Tip (vérifier la génération d\'avoir)');
        $this->line('  Renseigner les tableaux de résultats dans docs/exploitation.md.');

        $this->newLine();
        if ($gateFailures !== []) {
            $this->error('NO-GO automatique : '.count($gateFailures).' gate(s) en échec — corriger avant les drills manuels.');

            return self::FAILURE;
        }

        $this->info('GO automatisable : les gates parité + missions bloquées sont au vert. Place aux drills manuels.');

        return self::SUCCESS;
    }
}
