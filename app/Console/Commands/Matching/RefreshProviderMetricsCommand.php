<?php

namespace App\Console\Commands\Matching;

use App\Models\User;
use App\Services\Matching\ProviderPerformanceCalculator;
use Illuminate\Console\Command;

class RefreshProviderMetricsCommand extends Command
{
    protected $signature = 'matching:refresh-metrics {--user= : Provider user_id (optionnel, sinon tous)} {--window=30 : Fenêtre rolling en jours}';

    protected $description = 'Recalcule les métriques de performance des providers (acceptance/completion/response/rating)';

    public function handle(ProviderPerformanceCalculator $calculator): int
    {
        $windowDays = (int) $this->option('window');
        $userId = $this->option('user');

        $query = User::query()
            ->whereHas('providerProfile', function ($q) {
                $q->where('status', 'active');
            });

        if ($userId) {
            $query->where('id', (int) $userId);
        }

        $count = 0;
        $query->chunkById(100, function ($providers) use ($calculator, $windowDays, &$count) {
            foreach ($providers as $provider) {
                try {
                    $calculator->calculate($provider, $windowDays);
                    $count++;
                } catch (\Throwable $e) {
                    $this->error("Erreur pour user #{$provider->id}: " . $e->getMessage());
                    report($e);
                }
            }
        });

        $this->info("Métriques recalculées pour {$count} provider(s) (fenêtre {$windowDays}j).");

        return self::SUCCESS;
    }
}
