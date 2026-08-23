<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Services\Payments\CommissionService;
use App\Services\Payments\ProviderWalletService;
use App\Support\International\Devise;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Stripe\Stripe;
use Stripe\Transfer;

class ProcessProviderPayouts extends Command
{
    protected $signature = 'payouts:process {--dry-run : Preview without writing}';

    protected $description = 'Process pending provider payouts for completed bookings';

    public function handle(CommissionService $commission, ProviderWalletService $wallet): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $bookings = Booking::where(function ($q) {
            $q->where('status', 'completed')
                ->orWhere('status', 'termine')
                ->orWhere('status', 'done');
        })
            ->whereNull('payout_status')
            // Audit HIGH — gel du payout tant qu'un litige n'est pas résolu : on
            // ne libère pas la rémunération du prestataire pendant qu'une réclamation
            // est ouverte sur la prestation.
            ->whereDoesntHave('complaintCases', function ($q) {
                $q->whereNotIn('status', ComplaintCase::FINAL_STATUSES);
            })
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

        $this->info("Found {$bookings->count()} bookings to process.".($dryRun ? ' [DRY RUN]' : ''));

        $totalPlatformFee = 0;
        $totalProviderPayout = 0;
        $processed = 0;
        $failed = 0;

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
                    DB::transaction(function () use ($booking, $calc, $wallet) {
                        // M10 — re-fetch under a row lock to close the TOCTOU window between the
                        // unlocked ->get() above and this update (a concurrent manual run could
                        // otherwise process the same booking twice). Skip if already processed.
                        $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();
                        if (! $locked || $locked->payout_status !== null) {
                            return;
                        }

                        // A booking captured via Stripe destination charge has already had
                        // the provider share transferred to their Connect account at capture
                        // time. Mark it 'auto_transferred' so Phase 2 below never re-transfers
                        // it (double payment). Only bookings that were NOT auto-paid this way
                        // stay 'processed' and remain eligible for a manual Phase 2 transfer.
                        $autoTransferred = $locked->payment_status === 'captured'
                            && ! empty($locked->stripe_payment_intent_id);

                        $updates = [
                            'payout_status' => $autoTransferred ? 'auto_transferred' : 'processed',
                            'platform_fee_cents' => $calc['platform_fee_cents'],
                        ];

                        // Populate both payout columns when present so the wallet service (which
                        // reads provider_amount_cents) credits the correct net amount.
                        if (Schema::hasColumn('bookings', 'provider_payout_cents')) {
                            $updates['provider_payout_cents'] = $calc['provider_payout_cents'];
                        }
                        if (Schema::hasColumn('bookings', 'provider_amount_cents')) {
                            $updates['provider_amount_cents'] = $calc['provider_payout_cents'];
                        }

                        // `forceFill()` : les colonnes d'argent sont hors de la liste blanche de
                        // `Booking`, pour qu'aucun tableau venu d'une requête ne les atteigne. Ici
                        // c'est la commande de versement qui écrit, et elle le dit.
                        $locked->forceFill($updates)->save();

                        // M5 — credit the wallet through the idempotent service instead of a raw
                        // insert. The previous raw insert mapped non-existent columns
                        // (user_id/provider_id/amount_cents) and never set the NOT NULL columns
                        // provider_user_id/direction/occurred_at, so it actually failed whenever a
                        // provider was present; recordEarning() dedupes via idempotency_key and
                        // keeps the ledger consistent with completeMission().
                        $wallet->recordEarning($locked);
                    });
                    $processed++;
                } catch (\Throwable $e) {
                    $this->error("  Booking #{$booking->id} failed: {$e->getMessage()}");
                    Log::error('[payouts] booking processing failed', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                    $failed++;

                    continue;
                }
            }

            $totalPlatformFee += $calc['platform_fee_cents'];
            $totalProviderPayout += $calc['provider_payout_cents'];
        }

        $this->info('Platform fee total  : €'.number_format($totalPlatformFee / 100, 2));
        $this->info('Provider payout total: €'.number_format($totalProviderPayout / 100, 2));

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
            // Audit HIGH — ne jamais transférer un booking sous litige non résolu.
            ->whereDoesntHave('complaintCases', function ($q) {
                $q->whereNotIn('status', ComplaintCase::FINAL_STATUSES);
            })
            // Defense-in-depth: never manually transfer a booking that was already paid
            // via a Stripe destination charge (provider auto-credited at capture). Such a
            // booking has a captured PaymentIntent. This excludes legacy 'processed' rows
            // written before A1 was fixed, in addition to the 'auto_transferred' marking.
            ->where(function ($q) {
                $q->where('payment_status', '!=', 'captured')
                    ->orWhereNull('payment_status')
                    ->orWhereNull('stripe_payment_intent_id');
            })
            ->with(['employe', 'assignedProvider'])
            ->get();

        if ($pendingTransfers->isEmpty()) {
            $this->info('  No manual transfers needed (all payouts auto-transferred via destination charges).');
        } else {
            $stripeKey = config('cashier.secret');

            foreach ($pendingTransfers as $booking) {
                $provider = $booking->assignedProvider ?? $booking->employe;
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
                    Stripe::setApiKey($stripeKey);

                    // Deterministic idempotency key: if the process crashes between Stripe
                    // creating the transfer and the local DB update below, the next run
                    // re-selects this booking and calls Transfer::create again — Stripe then
                    // returns the SAME transfer instead of creating a second real one. (A2)
                    $transfer = Transfer::create(
                        [
                            'amount' => $payoutCents,
                            // LA DEVISE DU TRANSFERT EST CELLE DE LA RESERVATION.
                            'currency' => Devise::pourStripe($booking->currency),
                            'destination' => $connectId,
                            'metadata' => [
                                // Stripe n'accepte QUE des chaines en metadonnee. Un entier
                                // passait jusqu'ici parce que la devise litterale figeait la
                                // forme du tableau ; l'analyse statique le voit desormais.
                                'booking_id' => (string) $booking->id,
                                'type' => 'manual_payout',
                            ],
                        ],
                        ['idempotency_key' => 'payout:booking:'.$booking->id]
                    );

                    $booking->forceFill([
                        'stripe_transfer_id' => $transfer->id,
                        'payout_status' => 'transferred',
                    ])->save();

                    $this->line("  Booking #{$booking->id}: transferred {$payoutCents}c -> {$connectId} ({$transfer->id})");
                } catch (\Throwable $e) {
                    $this->error("  Booking #{$booking->id}: transfer failed — {$e->getMessage()}");
                    Log::error('[payouts] Stripe Transfer failed', [
                        'booking_id' => $booking->id,
                        'connect_id' => $connectId,
                        'error' => $e->getMessage(),
                    ]);
                    $failed++;
                }
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
