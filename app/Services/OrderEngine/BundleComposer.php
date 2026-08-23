<?php

namespace App\Services\OrderEngine;

use App\Models\OrderDraft;
use App\Models\OrderDraftItem;
use App\Models\Trade;
use App\Models\TradeBundleSuggestion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Le panier multi-métiers : ajouter, ordonner, planifier. */
class BundleComposer
{
    public function __construct(
        protected OrderDraftManager $drafts,
        protected PricingEngine $pricing,
    ) {}

    /** Ajoute un métier au panier, à sa place dans la séquence. */
    public function addTrade(OrderDraft $draft, Trade $trade): OrderDraftItem
    {
        $item = $this->drafts->itemFor($draft, $trade);

        $dependency = $this->dependencyFor($draft, $trade);

        if ($dependency) {
            $item->update([
                'depends_on_item_id' => $dependency['item']->id,
                'sequence_gap_min' => $dependency['gap'],
                // La ligne se place juste après celle dont elle dépend, et repousse les suivantes.
                'sequence' => $dependency['item']->sequence + 1,
            ]);

            $this->shiftAfter($draft, $item);
        }

        return $item->fresh();
    }

    /** Retire un métier, sans laisser de dépendance orpheline derrière lui. */
    public function removeTrade(OrderDraft $draft, OrderDraftItem $item): void
    {
        DB::transaction(function () use ($draft, $item) {
            // Les lignes qui dépendaient de celle-ci sont RATTACHÉES à sa propre dépendance, pas détachées.
            OrderDraftItem::where('order_draft_id', $draft->id)
                ->where('depends_on_item_id', $item->id)
                ->update([
                    'depends_on_item_id' => $item->depends_on_item_id,
                    'sequence_gap_min' => $item->depends_on_item_id ? $item->sequence_gap_min : 0,
                ]);

            $item->delete();
            $this->renumber($draft);
        });
    }

    /**
     * « Souvent commandé avec » — les métiers que l'administrateur a associés.
     *
     * @return Collection<int, array{trade: Trade, gap_min: int, after: string}>
     */
    public function suggestionsFor(OrderDraft $draft): Collection
    {
        $present = $draft->items()->pluck('trade_id');

        if ($present->isEmpty()) {
            return collect();
        }

        return TradeBundleSuggestion::query()
            ->whereIn('trade_id', $present)
            ->whereNotIn('suggested_trade_id', $present)
            ->where('is_active', true)
            ->with(['suggestedTrade', 'trade'])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (TradeBundleSuggestion $s) => $s->suggestedTrade?->is_active)
            // Deux métiers du panier peuvent suggérer le même troisième : on ne le propose qu'une fois.
            ->unique('suggested_trade_id')
            ->map(fn (TradeBundleSuggestion $s) => [
                'trade' => $s->suggestedTrade,
                'gap_min' => (int) $s->default_sequence_gap_min,
                'after' => $s->trade?->translate('name') ?? '',
            ])
            ->values();
    }

    /**
     * La timeline : chaque métier, son ordre, et quand il peut commencer.
     *
     * @return Collection<int, array{item: OrderDraftItem, trade: Trade, starts_at: Carbon, ends_at: Carbon, waits_for: string|null, gap_min: int}>
     */
    public function timeline(OrderDraft $draft, ?Carbon $startAt = null): Collection
    {
        $startAt ??= $draft->scheduled_at ?? Carbon::now()->addDay()->setTime(8, 0);
        $items = $draft->items()->with('trade')->orderBy('sequence')->orderBy('id')->get();

        $endsById = [];
        $cursor = $startAt->copy();

        return $items->map(function (OrderDraftItem $item) use (&$endsById, &$cursor) {
            $duration = max(30, (int) ($item->duration_min ?: $item->trade?->estimated_duration_min ?: 60));

            // UNE DATE ÉPINGLÉE l'emporte sur la séquence.
            // Une ligne dépendante démarre après la FIN de celle dont elle dépend, plus le délai.
            // Les autres s'enchaînent simplement.
            $start = match (true) {
                $item->scheduled_at !== null => $item->scheduled_at->copy(),
                (bool) $item->depends_on_item_id && isset($endsById[$item->depends_on_item_id]) => $endsById[$item->depends_on_item_id]->copy()->addMinutes((int) $item->sequence_gap_min),
                default => $cursor->copy(),
            };

            $end = $start->copy()->addMinutes($duration);

            $endsById[$item->id] = $end;
            $cursor = $end->copy();

            return [
                'item' => $item,
                'trade' => $item->trade,
                'starts_at' => $start,
                'ends_at' => $end,
                'waits_for' => $item->dependsOn?->trade?->name,
                'gap_min' => (int) $item->sequence_gap_min,
            ];
        });
    }

    /**
     * Fixe la date d'UN métier du chantier.
     *
     * @throws ValidationException si la date est passée ou viole une dépendance
     */
    public function pinItemDate(OrderDraft $draft, OrderDraftItem $item, Carbon $when): OrderDraftItem
    {
        if ($when->isPast()) {
            throw ValidationException::withMessages([
                'item_date' => 'Cette date est déjà passée. Choisissez un jour à venir.',
            ]);
        }

        $blocker = $item->depends_on_item_id
            ? $draft->items()->with('trade')->find($item->depends_on_item_id)
            : null;

        if ($blocker) {
            $line = $this->timeline($draft)->firstWhere('item.id', $blocker->id);
            $earliest = $line
                ? $line['ends_at']->copy()->addMinutes((int) $item->sequence_gap_min)
                : null;

            if ($earliest && $when->lt($earliest)) {
                throw ValidationException::withMessages([
                    'item_date' => sprintf(
                        '« %s » ne peut pas commencer avant la fin de « %s ». Au plus tôt le %s.',
                        $item->trade?->translate('name') ?? 'Ce métier',
                        $blocker->trade?->translate('name') ?? 'le métier précédent',
                        $earliest->translatedFormat('l j F à H\hi'),
                    ),
                ]);
            }
        }

        $item->update(['scheduled_at' => $when]);

        return $item->fresh();
    }

    /** Retour à la séquence automatique, sans avoir à tout refaire. */
    public function releaseItemDate(OrderDraft $draft, OrderDraftItem $item): OrderDraftItem
    {
        $item->update(['scheduled_at' => null]);

        return $item->fresh();
    }

    /**
     * Réordonne à la main — en refusant ce qui casserait le chantier.
     *
     * @param  list<int>  $orderedItemIds
     *
     * @throws ValidationException si une dépendance est violée
     */
    public function reorder(OrderDraft $draft, array $orderedItemIds): void
    {
        $items = $draft->items()->with('trade')->get()->keyBy('id');
        $positions = array_flip(array_values($orderedItemIds));

        foreach ($items as $item) {
            if ($item->depends_on_item_id === null || ! isset($positions[$item->id])) {
                continue;
            }

            $dependencyPosition = $positions[$item->depends_on_item_id] ?? null;

            if ($dependencyPosition === null || $dependencyPosition > $positions[$item->id]) {
                $blocker = $items[$item->depends_on_item_id] ?? null;

                throw ValidationException::withMessages([
                    'sequence' => [sprintf(
                        '« %s » ne peut pas passer avant « %s » : il faut attendre la fin de cette intervention.',
                        $item->trade?->translate('name') ?? 'Ce service',
                        $blocker?->trade?->translate('name') ?? 'l’intervention précédente',
                    )],
                ]);
            }
        }

        DB::transaction(function () use ($items, $orderedItemIds) {
            foreach (array_values($orderedItemIds) as $position => $itemId) {
                $items[$itemId]?->update(['sequence' => $position]);
            }
        });
    }

    /**
     * Le devis consolidé : un total, le détail dépliable par métier, la remise visible.
     *
     * @return array{order: PriceBreakdown, items: Collection<int, array{item: OrderDraftItem, trade: Trade, quote: PriceBreakdown}>}
     */
    public function consolidatedQuote(OrderDraft $draft): array
    {
        $items = $draft->items()->with('trade')->orderBy('sequence')->get();

        $resolver = app(ZonePricingResolver::class);
        $zoneId = $draft->service_zone_id ? (int) $draft->service_zone_id : null;

        $detailed = $items->map(function (OrderDraftItem $item) use ($draft, $resolver, $zoneId) {
            $questions = $item->trade->questions()->with(['options.translations', 'conditions', 'translations'])->get();

            return [
                'item' => $item,
                'trade' => $item->trade,
                'quote' => $this->pricing->quoteItem(
                    $item->trade,
                    $questions,
                    $this->drafts->answersOf($item->load('answers')),
                    // LA MÊME GRILLE QU'À L'ÉCRAN. C'est ce devis-ci qui est FIGÉ à la
                    // confirmation : le calculer sans la zone donnerait au client un prix affiché
                    // et un prix facturé différents.
                    // `purchased_minutes` voyage AVEC le contexte, depuis la ligne de panier.
                    ['mode' => $draft->mode, 'purchased_minutes' => $item->purchased_minutes]
                        + $resolver->pricingContext((int) $item->trade_id, $zoneId, $draft),
                ),
            ];
        });

        return [
            'order' => $this->pricing->quoteOrder($detailed->pluck('quote')->all(), $draft->mode),
            'items' => $detailed,
        ];
    }

    /**
     * La ligne dont un métier ajouté doit dépendre, s'il y en a une.
     *
     * @return array{item: OrderDraftItem, gap: int}|null
     */
    protected function dependencyFor(OrderDraft $draft, Trade $trade): ?array
    {
        $existing = $draft->items()->with('trade')->orderBy('sequence')->get();

        foreach ($existing as $item) {
            if ($item->trade_id === $trade->id) {
                continue;
            }

            $suggestion = TradeBundleSuggestion::query()
                ->where('trade_id', $item->trade_id)
                ->where('suggested_trade_id', $trade->id)
                ->where('is_active', true)
                ->first();

            // Seule une association PORTANT UN DÉLAI exprime une dépendance. « Souvent commandé
            // avec » sans délai est une suggestion commerciale, pas une contrainte de chantier :
            // en faire une dépendance interdirait au client de réordonner librement.
            if ($suggestion && $suggestion->default_sequence_gap_min > 0) {
                return ['item' => $item, 'gap' => (int) $suggestion->default_sequence_gap_min];
            }
        }

        return null;
    }

    /** Décale les lignes qui suivent celle qu'on vient d'insérer. */
    protected function shiftAfter(OrderDraft $draft, OrderDraftItem $inserted): void
    {
        OrderDraftItem::where('order_draft_id', $draft->id)
            ->where('id', '!=', $inserted->id)
            ->where('sequence', '>=', $inserted->sequence)
            ->increment('sequence');
    }

    /** Renumérote sans trous, pour que l'ordre affiché reste lisible. */
    protected function renumber(OrderDraft $draft): void
    {
        $draft->items()->orderBy('sequence')->orderBy('id')->get()
            ->each(fn (OrderDraftItem $item, int $index) => $item->update(['sequence' => $index]));
    }
}
