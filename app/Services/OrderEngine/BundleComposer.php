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
                'after' => $s->trade->name ?? '',
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

            /*
             * UNE DATE ÉPINGLÉE l'emporte sur la séquence.
             *
             * La séquence calculée est le bon défaut : le client n'a pas à orchestrer ses artisans.
             * Mais elle n'est pas toujours la réalité — le plombier passe mardi parce qu'il n'a que
             * mardi. La date posée à la main devient alors le point de départ de cette ligne, et
             * les suivantes s'enchaînent derrière.
             */
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
     * « Soit une date unique pour tout, soit une date par métier » : la séquence calculée reste le
     * défaut, et celle-ci l'ouvre au cas réel — le plombier ne peut que mardi, le reste suit.
     *
     * DEUX REFUS, tous deux annoncés plutôt que corrigés en silence. Une date passée ne se planifie
     * pas. Et un métier ne se pose pas avant la fin de celui dont il dépend : le carreleur avant le
     * plombier est un chantier impossible, et corriger discrètement ferait croire au client que sa
     * date a été prise en compte — il découvrirait autre chose le jour venu.
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
                        $item->trade->name ?? 'Ce métier',
                        $blocker->trade->name ?? 'le métier précédent',
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
                        $item->trade->name ?? 'Ce service',
                        $blocker?->trade->name ?? 'l’intervention précédente',
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
                    ['mode' => $draft->mode] + $resolver->pricingContext((int) $item->trade_id, $zoneId),
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
