<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Payments\CommissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProcessProviderPayouts extends Command
{
    protected $signature = 'payouts:process {--dry-run : Preview without writing}';
    protected $description = 'Process pending provider payouts for completed bookings';

    public function handle(CommissionService $commission): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $bookings = Booking::where(function ($q) {
            $q->where('status', 'completed')
              ->orWhere('status', 'termine')
              ->orWhere('status', 'done');
        })
            ->whereNull('payout_status')
            ->where(function ($q) {
                $q->whereNotNull('devis_estime')
                  ->orWhereNotNull('estimated_price')
                  ->orWhereNotNull('payment_amount_cents');
            })
            ->with([
                'employe',
                'employe.providerProfile',
                'assignedProvider',
                'assignedProvider.providerProfile',
            ])
            ->get();

        $this->info("Found {$bookings->count()} bookings to process." . ($dryRun ? ' [DRY RUN]' : ''));

        $totalPlatformFee     = 0;
        $totalProviderPayout  = 0;
        $processed            = 0;
        $failed               = 0;

        foreach ($bookings as $booking) {
            $calc = $commission->calculateForBooking($booking);

            if ($dryRun) {
                $this->line(sprintf(
                    '  Booking #%s: total=%dc  platform=%dc  provider=%dc  rate=%.0f%%',
                    $booking->id,
                    $calc['total_cents'],
                    $calc['platform_fee_cents'],
                    $calc['provider_payout_cents'],
                    $calc['commission_rate'] * 100
                ));
            } else {
                try {
                    DB::transaction(function () use ($booking, $calc) {
                        $updates = [
                            'payout_status'        => 'processed',
                            'platform_fee_cents'   => $calc['platform_fee_cents'],
                        ];

                        // Map to whichever column exists
                        if (Schema::hasColumn('bookings', 'provider_payout_cents')) {
                            $updates['provider_payout_cents'] = $calc['provider_payout_cents'];
                        } elseif (Schema::hasColumn('bookings', 'provider_amount_cents')) {
                            $updates['provider_amount_cents'] = $calc['provider_payout_cents'];
                        }

                        $booking->fill($updates)->save();

                        // Soft-insert into wallet ledger if table exists
                        if (Schema::hasTable('provider_wallet_transactions')) {
                            $providerId = $booking->assigned_provider_user_id
                                ?? $booking->employe_id
                                ?? null;

                            if ($providerId) {
                                $cols = DB::getSchemaBuilder()->getColumnListing('provider_wallet_transactions');

                                $row = [
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];

                                // Map flexible column names
                                if (in_array('user_id', $cols))       $row['user_id']    = $providerId;
                                if (in_array('provider_id', $cols))   $row['provider_id'] = $providerId;
                                if (in_array('booking_id', $cols))    $row['booking_id'] = $booking->id;
                                if (in_array('type', $cols))          $row['type']       = 'earning';
                                if (in_array('amount', $cols))        $row['amount']     = $calc['provider_payout_cents'] / 100;
                                if (in_array('amount_cents', $cols))  $row['amount_cents'] = $calc['provider_payout_cents'];
                                if (in_array('currency', $cols))      $row['currency']   = 'eur';
                                if (in_array('description', $cols))   $row['description'] = "Paiement mission #{$booking->id}";
                                if (in_array('status', $cols))        $row['status']     = 'available';

                                DB::table('provider_wallet_transactions')->insert($row);
                            }
                        }
                    });
                    $processed++;
                } catch (\Throwable $e) {
                    $this->error("  Booking #{$booking->id} failed: {$e->getMessage()}");
                    Log::error('[payouts] booking processing failed', [
                        'booking_id' => $booking->id,
                        'error'      => $e->getMessage(),
                    ]);
                    $failed++;
                    continue;
                }
            }

            $totalPlatformFee    += $calc['platform_fee_cents'];
            $totalProviderPayout += $calc['provider_payout_cents'];
        }

        $this->info('Platform fee total  : €' . number_format($totalPlatformFee / 100, 2));
        $this->info('Provider payout total: €' . number_format($totalProviderPayout / 100, 2));

        if (! $dryRun) {
            $this->info("Processed: {$processed}  Failed: {$failed}");
        }

        // ─────────────────────────────────────────────────────────────────
        // Phase 2: Stripe Transfers for manual payouts
        // For edge-case bookings NOT covered by destination charges
        // (provider had no Connect account at authorization time).
        // Auto-transferred bookings (transfer_data.destination was set at
        // authorize time) already have funds on the Connect account and
        // must NOT be transferred again.
        // ─────────────────────────────────────────────────────────────────
        $this->info('');
        $this->info('Phase 2: Stripe Transfers for processed payouts without auto-transfer...');

        $pendingTransfers = Booking::where('payout_status', 'processed')
            ->whereNull('stripe_transfer_id')
            ->with(['employe', 'assignedProvider'])
            ->get();

        if ($pendingTransfers->isEmpty()) {
            $this->info('  No manual transfers needed (all payouts auto-transferred via destination charges).');
        } else {
            $stripeKey = config('cashier.secret');

            foreach ($pendingTransfers as $booking) {
                $provider  = $booking->assignedProvider ?? $booking->employe;
                $connectId = $provider?->stripe_connect_account_id;

                if (! $connectId) {
                    $this->warn("  Booking #{$booking->id}: provider has no Stripe Connect account — skipping.");
                    continue;
                }

                $payoutCents = $booking->provider_payout_cents
                    ?? $booking->provider_amount_cents
                    ?? null;

                if (! $payoutCents || $payoutCents <= 0) {
                    $this->warn("  Booking #{$booking->id}: no payout amount — skipping.");
                    continue;
                }

                if ($dryRun) {
                    $this->line("  [DRY] Transfer {$payoutCents}c to {$connectId} for booking #{$booking->id}");
                    continue;
                }

                if (! $stripeKey) {
                    $this->warn("  Stripe key not configured — skipping manual transfer for booking #{$booking->id}.");
                    continue;
                }

                try {
                    \Stripe\Stripe::setApiKey($stripeKey);

                    $transfer = \Stripe\Transfer::create([
                        'amount'      => $payoutCents,
                        'currency'    => 'eur',
                        'destination' => $connectId,
                        'metadata'    => [
                            'booking_id' => $booking->id,
                            'type'       => 'manual_payout',
                        ],
                    ]);

                    $booking->update([
                        'stripe_transfer_id' => $transfer->id,
                        'payout_status'      => 'transferred',
                    ]);

                    $this->line("  Booking #{$booking->id}: transferred {$payoutCents}c -> {$connectId} ({$transfer->id})");
                } catch (\Throwable $e) {
                    $this->error("  Booking #{$booking->id}: transfer failed — {$e->getMessage()}");
                    Log::error('[payouts] Stripe Transfer failed', [
                        'booking_id' => $booking->id,
                        'connect_id' => $connectId,
                        'error'      => $e->getMessage(),
                    ]);
                    $failed++;
                }
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
