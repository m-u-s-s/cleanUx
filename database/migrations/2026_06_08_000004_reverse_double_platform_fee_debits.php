<?php

use App\Models\ProviderWalletTransaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** M4 — correct historical wallet balances after removing the erroneous platform-fee debit. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_wallet_transactions')) {
            return;
        }

        ProviderWalletTransaction::query()
            ->where('type', ProviderWalletTransaction::TYPE_PLATFORM_FEE)
            ->where('direction', ProviderWalletTransaction::DIRECTION_DEBIT)
            ->orderBy('id')
            ->chunkById(500, function ($debits) {
                foreach ($debits as $debit) {
                    $reversalKey = ($debit->idempotency_key ?? ('fee:'.$debit->id)).':m4_reversal';

                    $exists = ProviderWalletTransaction::query()
                        ->where('idempotency_key', $reversalKey)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    ProviderWalletTransaction::create([
                        'provider_user_id' => $debit->provider_user_id,
                        'type' => ProviderWalletTransaction::TYPE_ADJUSTMENT_CREDIT,
                        'direction' => ProviderWalletTransaction::DIRECTION_CREDIT,
                        'amount' => $debit->amount,
                        'currency' => $debit->currency,
                        'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
                        'source_type' => $debit->source_type,
                        'source_id' => $debit->source_id,
                        'idempotency_key' => $reversalKey,
                        'description' => 'Correction M4 : annulation du débit commission en double',
                        'occurred_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('provider_wallet_transactions')) {
            return;
        }

        ProviderWalletTransaction::query()
            ->where('type', ProviderWalletTransaction::TYPE_ADJUSTMENT_CREDIT)
            ->where('idempotency_key', 'like', '%:m4_reversal')
            ->delete();
    }
};
