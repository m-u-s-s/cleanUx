<?php

namespace App\Console\Commands\Loyalty;

use App\Models\LoyaltyAccount;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Console\Command;

class ReevaluateTiersCommand extends Command
{
    protected $signature = 'loyalty:reevaluate-tiers {--user= : User id spécifique} {--dry-run}';

    protected $description = 'Recalcule le tier de chaque LoyaltyAccount selon la fenêtre roulante (déclenche downgrade si besoin)';

    public function handle(LoyaltyService $service): int
    {
        $userId = $this->option('user');
        $dryRun = (bool) $this->option('dry-run');

        $query = LoyaltyAccount::query()->with('user');
        if ($userId) {
            $query->where('user_id', (int) $userId);
        }

        $upgrades = 0;
        $downgrades = 0;
        $unchanged = 0;

        $query->chunkById(100, function ($accounts) use ($service, $dryRun, &$upgrades, &$downgrades, &$unchanged) {
            foreach ($accounts as $account) {
                if (! $account->user) continue;

                $previousTierId = $account->current_tier_id;

                if ($dryRun) {
                    $newTier = app(\App\Services\Loyalty\LoyaltyTierEvaluator::class)->evaluate($account);
                } else {
                    $service->reevaluateAndNotify($account, $account->user);
                }

                $account->refresh();
                if ((int) $account->current_tier_id === (int) $previousTierId) {
                    $unchanged++;
                } elseif (! $previousTierId || $account->currentTier?->rank > ($account->currentTier ? $previousTierId : 0)) {
                    // simplifié pour le compteur (ne distingue pas up/down si dryRun)
                    $upgrades++;
                } else {
                    $downgrades++;
                }
            }
        });

        $this->table(['Type', 'Count'], [
            ['Upgrades', $upgrades],
            ['Downgrades', $downgrades],
            ['Unchanged', $unchanged],
        ]);

        return self::SUCCESS;
    }
}
