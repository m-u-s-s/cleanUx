<?php

namespace App\Services\Finance;

use App\Models\CustomerCredit;
use App\Models\RendezVous;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

class CustomerCreditApplicationService
{
    public function applyAvailableCredits(User $client, RendezVous $rdv): float
    {
        if ($rdv->devis_estime <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($client, $rdv) {
            $remainingToPay = (float) $rdv->devis_estime;
            $totalApplied = 0;

            $credits = CustomerCredit::query()
                ->where('client_id', $client->id)
                ->where('status', 'active')
                ->where('remaining_amount', '>', 0)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->oldest()
                ->lockForUpdate()
                ->get();

            foreach ($credits as $credit) {
                if ($remainingToPay <= 0) {
                    break;
                }

                $amountToApply = min((float) $credit->remaining_amount, $remainingToPay);

                $credit->remaining_amount = round((float) $credit->remaining_amount - $amountToApply, 2);

                if ($credit->remaining_amount <= 0) {
                    $credit->status = 'used';
                    $credit->remaining_amount = 0;
                }

                $credit->save();

                $remainingToPay -= $amountToApply;
                $totalApplied += $amountToApply;
            }

            if ($totalApplied > 0) {
                $rdv->devis_estime = round(max(0, (float) $rdv->devis_estime - $totalApplied), 2);

                $snapshot = (array) ($rdv->pricing_snapshot ?? []);
                $snapshot['customer_credit_applied'] = round($totalApplied, 2);
                $snapshot['devis_after_credit'] = $rdv->devis_estime;

                $rdv->pricing_snapshot = $snapshot;
                $rdv->save();

                ActivityLogger::log('customer_credit_applied_to_booking', $rdv, [
                    'client_id' => $client->id,
                    'rendez_vous_id' => $rdv->id,
                    'amount_applied' => round($totalApplied, 2),
                    'new_devis_estime' => $rdv->devis_estime,
                ]);
            }

            return round($totalApplied, 2);
        });
    }
}