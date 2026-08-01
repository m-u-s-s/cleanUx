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

/**
 * Le panier multi-métiers : ajouter, ordonner, planifier.
 *
 * Pensé pour le chantier réel — une douche à refaire, c'est un plombier ET un carreleur. Le client
 * ne gère qu'une commande, un suivi, un interlocuteur, même si trois professionnels différents
 * interviennent.
 *
 * L'ORDRE n'est pas cosmétique : le carreleur ne peut pas poser avant que le plombier ait fini, et
 * pas immédiatement après non plus — il faut laisser sécher. Ce délai est une donnée configurée
 * par l'administrateur sur chaque association, pas une constante écrite ici : lui seul sait
 * combien de temps sèche une chape.
 *
 * Les dépendances ne peuvent pas former de cycle PAR CONSTRUCTION : un métier ajouté ne dépend que
 * de lignes DÉJÀ présentes, donc strictement antérieures. Il n'y a rien à détecter, parce qu'il
 * n'y a rien à créer.
 */
class BundleComposer
{
    public function __construct(
        protected OrderDraftManager $drafts,
        protected PricingEngine $pricing,
    ) {}

    /**
     * Ajoute un métier au panier, à sa place dans la séquence.
     *
     * L'adresse, l'étage et les contraintes d'accès ne sont PAS redemandés : ils vivent sur la
     * commande, pas sur la ligne. Les redemander à chaque service ajouté est le frottement qui
     * fait abandonner un panier déjà rempli.
     */
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
            /*
             * Les lignes qui dépendaient de celle-ci sont RATTACHÉES à sa propre dépendance, pas
             * détachées. Retirer le plombier ne doit pas faire croire que le carreleur peut poser
             * en premier : s'il y avait une raison d'attendre, elle remonte d'un cran.
             */
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
     * Ceux déjà au panier sont écartés : proposer d'ajouter ce qu'on vient d'ajouter ferait douter
     * de tout le reste des suggestions.
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
                'after' => $s->trade?->name ?? '',
            ])
            ->values();
    }

    /**
     * La timeline : chaque métier, son ordre, et quand il peut commencer.
     *
     * Le décalage se PROPAGE : si le plombier prend deux heures et qu'il faut vingt-quatre heures
     * de séchage, le carreleur commence le lendemain — et le peintre après lui. Calculer chaque
     * date indépendamment produirait un planning où trois personnes arrivent en même temps.
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

            // Une ligne dépendante démarre après la FIN de celle dont elle dépend, plus le délai.
            // Les autres s'enchaînent simplement.
            $start = $item->depends_on_item_id && isset($endsById[$item->depends_on_item_id])
                ? $endsById[$item->depends_on_item_id]->copy()->addMinutes((int) $item->sequence_gap_min)
                : $cursor->copy();

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
     * Réordonne à la main — en refusant ce qui casserait le chantier.
     *
     * Le client peut vouloir passer le nettoyage avant la peinture ; il ne peut pas faire poser le
     * carrelage avant la plomberie. Corriger en silence serait pire que refuser : il croirait que
     * son geste a été pris en compte.
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
                        $item->trade?->name ?? 'Ce service',
                        $blocker?->trade?->name ?? 'l’intervention précédente',
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

        $detailed = $items->map(function (OrderDraftItem $item) use ($draft) {
            $questions = $item->trade->questions()->with(['options', 'conditions'])->get();

            return [
                'item' => $item,
                'trade' => $item->trade,
                'quote' => $this->pricing->quoteItem(
                    $item->trade,
                    $questions,
                    $this->drafts->answersOf($item->load('answers')),
                    ['mode' => $draft->mode],
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
