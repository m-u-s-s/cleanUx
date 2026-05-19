<?php

namespace App\Services\Quality;

use App\Models\QualityChecklist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QualityChecklistResolver — picks the most appropriate checklist for a
 * (mission, phase) couple. Schema-defensive sur la chaîne mission→booking→trade.
 *
 *   - Plus spécifique trade match wins (checklist.trade_codes contient le trade)
 *   - Sinon catch-all (trade_codes=null) gagne
 *   - Filtré par phase (matches exact OR 'all')
 *   - Filtré par is_active=true
 */
class QualityChecklistResolver
{
    public function resolveForMission(int $missionId, string $phase): ?QualityChecklist
    {
        $tradeCode = $this->resolveTradeFromMission($missionId);

        return $this->resolveByTradeAndPhase($tradeCode, $phase);
    }

    public function resolveByTradeAndPhase(?string $tradeCode, string $phase): ?QualityChecklist
    {
        $candidates = QualityChecklist::query()
            ->active()
            ->phase($phase)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Score : trade specific = 10, catch-all = 1. Prefer higher version when tied.
        $best = null;
        $bestScore = -1;

        foreach ($candidates as $checklist) {
            if (! $checklist->appliesToTrade($tradeCode)) {
                continue;
            }
            $score = $checklist->trade_codes && count($checklist->trade_codes) > 0 ? 10 : 1;
            $score = $score * 1000 + (int) $checklist->version;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $checklist;
            }
        }

        return $best;
    }

    public function resolveTradeFromMission(int $missionId): ?string
    {
        if (! Schema::hasTable('missions')) {
            return null;
        }

        $mission = DB::table('missions')->where('id', $missionId)->first();
        if (! $mission) {
            return null;
        }

        $serviceCatalogId = $mission->service_catalog_id ?? null;
        if (! $serviceCatalogId) {
            // Try via booking
            $bookingId = $mission->rendez_vous_id ?? $mission->booking_id ?? null;
            if ($bookingId && Schema::hasTable('bookings')) {
                $booking = DB::table('bookings')->where('id', $bookingId)->first();
                $serviceCatalogId = $booking->service_catalog_id ?? null;
            }
        }
        if (! $serviceCatalogId) {
            return null;
        }

        if (! Schema::hasTable('service_catalogs') || ! Schema::hasTable('trades')) {
            return null;
        }

        $service = DB::table('service_catalogs')->where('id', $serviceCatalogId)->first();
        if (! $service || ! ($service->trade_id ?? null)) {
            return null;
        }

        $trade = DB::table('trades')->where('id', $service->trade_id)->first();
        return $trade->code ?? null;
    }
}
