<?php

namespace App\Console\Commands\Payments;

use App\Services\Payments\StripeReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ReconcileStripeCommand extends Command
{
    protected $signature = 'stripe:reconcile {--scope=all : all|payment_intents|payouts} {--days=7 : Fenêtre en jours} {--from= : Date début (YYYY-MM-DD)} {--to= : Date fin (YYYY-MM-DD)}';

    protected $description = 'Compare les transactions Stripe avec la DB locale et liste les écarts';

    public function handle(StripeReconciliationService $service): int
    {
        $scope = (string) $this->option('scope');
        $days = (int) $this->option('days');

        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : now()->subDays($days)->startOfDay();

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : now()->endOfDay();

        $this->info("Réconciliation Stripe ({$scope}) du {$from->toDateString()} au {$to->toDateString()}…");

        try {
            $run = $service->run($scope, $from, $to);
        } catch (\Throwable $e) {
            $this->error("Erreur : {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->table(
            ['Statut', 'Items', 'Écarts', 'Critiques'],
            [[
                $run->status,
                $run->items_checked,
                $run->mismatches_found,
                $run->requires_attention,
            ]]
        );

        if ($run->mismatches_found > 0) {
            $this->warn("⚠ {$run->mismatches_found} écart(s) détecté(s) — voir admin /matching/reconciliation");
            foreach ((array) $run->mismatches as $m) {
                $this->line(sprintf(
                    '[%s] %s: %s',
                    $m['severity'] ?? '?',
                    $m['type'] ?? '?',
                    $m['message'] ?? ''
                ));
            }
        } else {
            $this->info('✓ Aucun écart détecté.');
        }

        return self::SUCCESS;
    }
}
