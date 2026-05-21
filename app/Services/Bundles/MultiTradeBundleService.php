<?php

namespace App\Services\Bundles;

use App\Models\MultiTradeBundle;
use App\Models\MultiTradeBundleItem;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service orchestrant les bundles multi-trades (rénovation, déménagement, etc.).
 *
 * Cas d'usage : client demande "rénovation salle de bain"
 *   → CleanUx propose 4 items (plomberie + carrelage + peinture + électricité)
 *   → Chaque item est quoted par provider du trade correspondant
 *   → Bundle accepté → 4 missions Booking créées en cascade (depends_on)
 *   → Facture consolidée + discount groupage (-10%)
 *
 * Différenciation FORT vs Uber/Bolt mono-vertical. Killer feature multi-trade.
 */
class MultiTradeBundleService
{
    /**
     * Crée un bundle draft avec items multi-trades.
     */
    public function createDraft(
        User $client,
        string $name,
        array $items,
        ?string $description = null,
        ?array $address = null,
    ): MultiTradeBundle {
        if (count($items) < 2) {
            throw ValidationException::withMessages([
                'items' => ['Un bundle doit contenir au moins 2 items multi-trades.'],
            ]);
        }

        return DB::transaction(function () use ($client, $name, $description, $items, $address) {
            $bundle = MultiTradeBundle::query()->create([
                'code' => MultiTradeBundle::generateCode(),
                'name' => $name,
                'description' => $description,
                'client_user_id' => $client->id,
                'status' => MultiTradeBundle::STATUS_DRAFT,
                'address' => $address,
                'currency' => 'EUR',
            ]);

            $totalEstimate = 0;
            foreach ($items as $idx => $itemData) {
                if (empty($itemData['trade_id']) || empty($itemData['label'])) {
                    throw ValidationException::withMessages([
                        'items' => ['Chaque item doit avoir trade_id + label.'],
                    ]);
                }
                $estimate = (int) ($itemData['estimated_price_cents'] ?? 0);
                $totalEstimate += $estimate;

                MultiTradeBundleItem::query()->create([
                    'bundle_id' => $bundle->id,
                    'trade_id' => (int) $itemData['trade_id'],
                    'label' => $itemData['label'],
                    'description' => $itemData['description'] ?? null,
                    'duration_minutes' => $itemData['duration_minutes'] ?? null,
                    'estimated_price_cents' => $estimate,
                    'sequence_order' => $idx,
                    'depends_on_item_ids' => $itemData['depends_on_item_ids'] ?? null,
                    'status' => MultiTradeBundleItem::STATUS_DRAFT,
                ]);
            }

            $bundle->update(['total_estimated_cents' => $totalEstimate]);
            return $bundle->fresh('items');
        });
    }

    /**
     * Demande quotes aux providers (transition draft → quoting).
     * Chaque item est dispatché à un provider du trade correspondant via Matching v2.
     */
    public function startQuoting(MultiTradeBundle $bundle): MultiTradeBundle
    {
        if ($bundle->status !== MultiTradeBundle::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Bundle non en draft.'],
            ]);
        }

        $bundle->update(['status' => MultiTradeBundle::STATUS_QUOTING]);

        // Pour MVP : on ne dispatch pas auto, on attend que les providers se manifestent
        // via UI ou que admin assigne manuellement. À enrichir avec MatchingV2Service.

        return $bundle->fresh();
    }

    /**
     * Provider quote un item du bundle.
     */
    public function quoteItem(
        MultiTradeBundleItem $item,
        User $provider,
        int $quotedPriceCents
    ): MultiTradeBundleItem {
        if (! in_array($item->status, [
            MultiTradeBundleItem::STATUS_DRAFT,
            MultiTradeBundleItem::STATUS_QUOTED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Item non quotable dans cet état.'],
            ]);
        }

        $item->update([
            'assigned_provider_user_id' => $provider->id,
            'quoted_price_cents' => $quotedPriceCents,
            'status' => MultiTradeBundleItem::STATUS_QUOTED,
        ]);

        $this->recomputeBundleTotal($item->bundle);

        return $item->fresh();
    }

    /**
     * Client accepte le bundle complet → transition vers accepted + crée les Bookings.
     */
    public function accept(MultiTradeBundle $bundle): MultiTradeBundle
    {
        if ($bundle->items()->where('status', '!=', MultiTradeBundleItem::STATUS_QUOTED)->exists()) {
            throw ValidationException::withMessages([
                'items' => ['Tous les items doivent être quotés avant acceptation.'],
            ]);
        }

        return DB::transaction(function () use ($bundle) {
            $bundle->update([
                'status' => MultiTradeBundle::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            // Pour chaque item, créer un Booking lié au provider qui a quoté
            foreach ($bundle->items as $item) {
                if (! $item->assigned_provider_user_id) {
                    continue;
                }
                try {
                    $booking = \App\Models\Booking::query()->create([
                        'client_id' => $bundle->client_user_id,
                        'employe_id' => $item->assigned_provider_user_id,
                        'trade_id' => $item->trade_id,
                        'devis_estime' => $item->quoted_price_cents / 100,
                        'duree_estimee' => $item->duration_minutes,
                        'status' => 'en_attente',
                        'commentaire_client' => $item->description ?? $item->label,
                        'matching_snapshot' => [
                            'source' => 'multi_trade_bundle',
                            'bundle_code' => $bundle->code,
                            'bundle_item_id' => $item->id,
                        ],
                    ]);
                    $item->update([
                        'booking_id' => $booking->id,
                        'status' => MultiTradeBundleItem::STATUS_ACCEPTED,
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[multi_trade] booking create failed', [
                        'bundle' => $bundle->code,
                        'item_id' => $item->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $bundle->fresh('items.booking');
        });
    }

    public function cancel(MultiTradeBundle $bundle, string $reason): MultiTradeBundle
    {
        $bundle->update([
            'status' => MultiTradeBundle::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'metadata' => array_merge($bundle->metadata ?? [], ['cancellation_reason' => $reason]),
        ]);

        // Cancel les items + bookings rattachés
        foreach ($bundle->items as $item) {
            $item->update(['status' => MultiTradeBundleItem::STATUS_CANCELLED]);
            if ($item->booking_id) {
                try {
                    \App\Models\Booking::query()->where('id', $item->booking_id)
                        ->update(['status' => 'annule', 'cancellation_reason' => 'bundle_cancelled']);
                } catch (\Throwable) {}
            }
        }

        return $bundle->fresh();
    }

    protected function recomputeBundleTotal(MultiTradeBundle $bundle): void
    {
        $quotedTotal = (int) $bundle->items()->sum('quoted_price_cents');
        $bundleDiscountCents = $bundle->bundle_discount_percent > 0
            ? (int) round($quotedTotal * ($bundle->bundle_discount_percent / 100))
            : (int) $bundle->bundle_discount_cents;

        $bundle->update([
            'total_quoted_cents' => $quotedTotal,
            'bundle_discount_cents' => $bundleDiscountCents,
            'status' => MultiTradeBundle::STATUS_QUOTED,
        ]);
    }
}
