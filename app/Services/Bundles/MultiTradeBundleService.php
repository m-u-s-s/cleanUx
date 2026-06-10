<?php

namespace App\Services\Bundles;

use App\Models\Booking;
use App\Models\MultiTradeBundle;
use App\Models\MultiTradeBundleItem;
use App\Models\MultiTradeBundleItemQuote;
use App\Models\Trade;
use App\Models\User;
use App\Notifications\Bundles\BundleQuoteRequestedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * Demande de devis aux prestataires (transition draft → quoting).
     *
     * Bundle Marketplace P1 : pour chaque item, on sollicite le top-N des
     * prestataires ÉLIGIBLES (bon métier + actif + KYC validé), on crée une
     * demande de devis `pending` valable N heures, et on les notifie. Les
     * prestataires soumettront ensuite leur prix (concurrence) et le client
     * comparera/choisira.
     */
    public function startQuoting(MultiTradeBundle $bundle): MultiTradeBundle
    {
        if ($bundle->status !== MultiTradeBundle::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Bundle non en draft.'],
            ]);
        }

        $topN = max(1, (int) config('bundles.quote_request_top_n', 5));
        $ttlHours = max(1, (int) config('bundles.quote_request_ttl_hours', 48));

        DB::transaction(function () use ($bundle, $topN, $ttlHours) {
            $bundle->update(['status' => MultiTradeBundle::STATUS_QUOTING]);

            foreach ($bundle->items as $item) {
                $this->solicitProvidersForItem($item, $topN, $ttlHours);
            }
        });

        return $bundle->fresh();
    }

    /**
     * Sollicite le top-N des prestataires éligibles pour un item et leur envoie
     * une demande de devis. Retourne le nombre de prestataires sollicités.
     */
    public function solicitProvidersForItem(MultiTradeBundleItem $item, int $topN, int $ttlHours): int
    {
        $validUntil = now()->addHours($ttlHours);
        $alreadySolicited = $item->quotes()->pluck('provider_user_id')->all();

        $providers = $this->eligibleProvidersForItem($item)
            ->reject(fn (User $provider) => in_array($provider->id, $alreadySolicited, true))
            ->take($topN);

        foreach ($providers as $provider) {
            MultiTradeBundleItemQuote::create([
                'bundle_item_id' => $item->id,
                'provider_user_id' => $provider->id,
                'status' => MultiTradeBundleItemQuote::STATUS_PENDING,
                'valid_until' => $validUntil,
            ]);

            try {
                $provider->notify(new BundleQuoteRequestedNotification($item));
            } catch (\Throwable $e) {
                Log::warning('[bundle] échec notification demande de devis', [
                    'item_id' => $item->id,
                    'provider_id' => $provider->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $providers->count();
    }

    /**
     * Prestataires éligibles pour quoter un item, classés par maîtrise du métier.
     * Éligibilité : actif + KYC validé (verification_status=verified) + habilité au
     * métier de l'item (pivot trade_user). Tri : proficiency puis métier primaire.
     */
    protected function eligibleProvidersForItem(MultiTradeBundleItem $item): Collection
    {
        $proficiencyRank = ['expert' => 4, 'advanced' => 3, 'intermediate' => 2, 'standard' => 1, 'basic' => 0];

        return User::query()
            ->where('is_active', true)
            ->whereHas('providerProfile', fn ($q) => $q
                ->where('status', 'active')
                ->where('verification_status', 'verified'))
            ->whereHas('trades', fn ($q) => $q->where('trades.id', $item->trade_id))
            ->with(['trades' => fn ($q) => $q->where('trades.id', $item->trade_id)])
            ->get()
            ->sortByDesc(function (User $provider) use ($item, $proficiencyRank) {
                $pivot = $provider->trades->firstWhere('id', $item->trade_id)?->pivot;
                $score = $proficiencyRank[$pivot?->proficiency ?? ''] ?? 0;

                return $score + (($pivot?->is_primary ?? false) ? 1 : 0);
            })
            ->values();
    }

    /**
     * P2 — Le prestataire sollicité soumet (ou met à jour) son devis pour un item.
     * Gardes : devis du prestataire, KYC validé, demande encore ouverte et non expirée.
     */
    public function submitQuote(
        MultiTradeBundleItemQuote $quote,
        User $provider,
        int $priceCents,
        ?string $message = null,
    ): MultiTradeBundleItemQuote {
        if ((int) $quote->provider_user_id !== (int) $provider->id) {
            throw ValidationException::withMessages([
                'quote' => ['Ce devis ne vous appartient pas.'],
            ]);
        }

        if (! $provider->hasClearedKyc()) {
            throw ValidationException::withMessages([
                'kyc' => ['Votre vérification d’identité (KYC) doit être validée pour proposer un devis.'],
            ]);
        }

        if (! in_array($quote->status, [
            MultiTradeBundleItemQuote::STATUS_PENDING,
            MultiTradeBundleItemQuote::STATUS_SUBMITTED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Cette demande de devis n’est plus ouverte.'],
            ]);
        }

        if ($quote->isExpired()) {
            throw ValidationException::withMessages([
                'expired' => ['Le délai pour répondre à cette demande est dépassé.'],
            ]);
        }

        if ($priceCents <= 0) {
            throw ValidationException::withMessages([
                'price_cents' => ['Le prix proposé doit être supérieur à zéro.'],
            ]);
        }

        $quote->update([
            'status' => MultiTradeBundleItemQuote::STATUS_SUBMITTED,
            'price_cents' => $priceCents,
            'message' => $message,
            'submitted_at' => now(),
        ]);

        return $quote->fresh();
    }

    /**
     * P2 — Le prestataire retire son devis (tant qu'il n'a pas été sélectionné).
     */
    public function withdrawQuote(
        MultiTradeBundleItemQuote $quote,
        User $provider,
    ): MultiTradeBundleItemQuote {
        if ((int) $quote->provider_user_id !== (int) $provider->id) {
            throw ValidationException::withMessages([
                'quote' => ['Ce devis ne vous appartient pas.'],
            ]);
        }

        if (! in_array($quote->status, [
            MultiTradeBundleItemQuote::STATUS_PENDING,
            MultiTradeBundleItemQuote::STATUS_SUBMITTED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Ce devis ne peut plus être retiré.'],
            ]);
        }

        $quote->update(['status' => MultiTradeBundleItemQuote::STATUS_WITHDRAWN]);

        return $quote->fresh();
    }

    /**
     * P3 — Le client retient un devis soumis pour un item.
     * Le devis choisi passe `selected`, les concurrents `rejected`, l'item est figé
     * sur le prestataire/prix choisi. Quand tous les items sont quotés, la remise
     * groupage est appliquée.
     */
    public function selectQuote(MultiTradeBundleItemQuote $quote): MultiTradeBundleItemQuote
    {
        if ($quote->status !== MultiTradeBundleItemQuote::STATUS_SUBMITTED) {
            throw ValidationException::withMessages([
                'status' => ['Seul un devis effectivement proposé peut être retenu.'],
            ]);
        }

        if ($quote->isExpired()) {
            throw ValidationException::withMessages([
                'expired' => ['Ce devis a expiré.'],
            ]);
        }

        return DB::transaction(function () use ($quote) {
            $item = $quote->item;

            // Rejette les devis concurrents encore ouverts pour cet item.
            MultiTradeBundleItemQuote::query()
                ->where('bundle_item_id', $item->id)
                ->where('id', '!=', $quote->id)
                ->whereIn('status', [
                    MultiTradeBundleItemQuote::STATUS_PENDING,
                    MultiTradeBundleItemQuote::STATUS_SUBMITTED,
                ])
                ->update(['status' => MultiTradeBundleItemQuote::STATUS_REJECTED]);

            $quote->update(['status' => MultiTradeBundleItemQuote::STATUS_SELECTED]);

            $item->update([
                'assigned_provider_user_id' => $quote->provider_user_id,
                'quoted_price_cents' => (int) $quote->price_cents,
                'status' => MultiTradeBundleItem::STATUS_QUOTED,
            ]);

            $bundle = $item->bundle;

            // Remise groupage : appliquée une fois TOUS les items quotés (≥ 2 items).
            $allQuoted = ! $bundle->items()
                ->where('status', '!=', MultiTradeBundleItem::STATUS_QUOTED)
                ->exists();

            if ($allQuoted
                && $bundle->items()->count() >= 2
                && (float) $bundle->bundle_discount_percent <= 0) {
                $bundle->update([
                    'bundle_discount_percent' => (float) config('bundles.group_discount_percent', 10),
                ]);
            }

            $this->recomputeBundleTotal($bundle->fresh());

            return $quote->fresh();
        });
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
                    $booking = Booking::query()->create([
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
                    Log::warning('[multi_trade] booking create failed', [
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
                    Booking::query()->where('id', $item->booking_id)
                        ->update(['status' => 'annule', 'cancellation_reason' => 'bundle_cancelled']);
                } catch (\Throwable) {
                }
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
