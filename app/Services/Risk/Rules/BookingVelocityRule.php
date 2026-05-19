<?php

namespace App\Services\Risk\Rules;

use App\Models\RiskEvaluation;
use App\Services\Risk\RiskContext;
use App\Services\Risk\RiskRuleHit;
use App\Services\Risk\RiskRuleInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Détecte une velocity anormale de créations de booking par le même user
 * dans une fenêtre courte (signal type bot / test card / fraude).
 *
 * Params:
 *   - window_minutes (default 60)
 *   - max_per_window (default 5)
 */
class BookingVelocityRule implements RiskRuleInterface
{
    public function code(): string
    {
        return 'booking.velocity';
    }

    public function evaluate(RiskContext $context, ?array $params = null): ?RiskRuleHit
    {
        if ($context->contextType !== RiskEvaluation::CONTEXT_BOOKING_CREATE) {
            return null;
        }

        $user = $context->user;
        if (! $user) {
            return null;
        }

        if (! Schema::hasTable('bookings')) {
            return null;
        }

        $windowMinutes = (int) ($params['window_minutes'] ?? 60);
        $max = (int) ($params['max_per_window'] ?? 5);

        $clientColumns = array_values(array_filter([
            Schema::hasColumn('bookings', 'client_id') ? 'client_id' : null,
            Schema::hasColumn('bookings', 'customer_user_id') ? 'customer_user_id' : null,
            Schema::hasColumn('bookings', 'created_by') ? 'created_by' : null,
        ]));
        if (empty($clientColumns)) {
            return null;
        }

        $count = DB::table('bookings')
            ->where(function ($q) use ($clientColumns, $user) {
                foreach ($clientColumns as $col) {
                    $q->orWhere($col, $user->id);
                }
            })
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        if ($count < $max) {
            return null;
        }

        $excess = $count - $max + 1;
        $score = min(60, 20 + $excess * 10);

        return new RiskRuleHit(
            code: $this->code(),
            score: $score,
            reason: "User a créé {$count} bookings dans les {$windowMinutes} dernières minutes (seuil {$max})",
            details: ['count' => $count, 'window_minutes' => $windowMinutes, 'max' => $max],
        );
    }
}
