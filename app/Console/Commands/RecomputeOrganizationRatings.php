<?php

namespace App\Console\Commands;

use App\Enums\OrganizationType;
use App\Models\OrganizationAccount;
use App\Services\Rating\OrganizationRatingAggregator;
use Illuminate\Console\Command;

class RecomputeOrganizationRatings extends Command
{
    protected $signature = 'organizations:recompute-ratings';

    protected $description = 'Recompute aggregated ratings for provider organizations from their workers.';

    public function handle(OrganizationRatingAggregator $aggregator): int
    {
        $types = [
            OrganizationType::PROVIDER_COMPANY->value,
            OrganizationType::HYBRID->value,
        ];

        $organizations = OrganizationAccount::whereIn('type', $types)->get();

        foreach ($organizations as $org) {
            $aggregator->recompute($org);
        }

        $this->info($organizations->count().' organizations recomputed.');

        return self::SUCCESS;
    }
}
