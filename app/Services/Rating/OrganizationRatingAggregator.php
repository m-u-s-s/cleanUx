<?php

namespace App\Services\Rating;

use App\Models\OrganizationAccount;
use Illuminate\Support\Facades\DB;

class OrganizationRatingAggregator
{
    public function recompute(OrganizationAccount $org): void
    {
        $row = DB::table('provider_profiles')
            ->where('organization_account_id', $org->id)
            ->whereNotNull('rating_avg')
            ->where('rating_count', '>', 0)
            ->selectRaw('SUM(rating_avg * rating_count) AS weighted, SUM(rating_count) AS total')
            ->first();

        $total = (int) ($row->total ?? 0);
        $avg = $total > 0 ? round(((float) $row->weighted) / $total, 2) : null;

        $org->forceFill(['rating_avg' => $avg, 'rating_count' => $total])->save();
    }

    public function recomputeForUser(int $userId): void
    {
        $orgId = DB::table('provider_profiles')->where('user_id', $userId)->value('organization_account_id');
        if ($orgId) {
            $org = OrganizationAccount::find($orgId);
            if ($org) {
                $this->recompute($org);
            }
        }
    }
}
