<?php

namespace App\Services\OrderEngine;

use App\Models\OrderDraftItem;
use App\Models\Trade;
use App\Support\Domain\OrderDraftStatus;
use Illuminate\Support\Collection;

/**
 * Quelle question fait décrocher les clients.
 *
 * C'est l'outil qui permet d'appliquer la règle des sept questions DANS LA DURÉE. Un parcours ne
 * devient pas trop long d'un coup : il s'allonge d'une question à la fois, chacune parfaitement
 * justifiable prise isolément, et la conversion s'érode sans que personne ne sache où.
 *
 * Deux chiffres distincts, et les confondre serait trompeur.
 *
 * Le TAUX DE RÉPONSE dit combien de clients ont répondu. Bas, il signale une question qu'on saute
 * — ce qui est souvent sain sur une question facultative.
 *
 * L'ABANDON dit combien se sont arrêtés LÀ, c'est-à-dire dont c'est la dernière question
 * renseignée d'une commande jamais confirmée. C'est celui-là qui coûte, et lui seul.
 */
class QuestionInsights
{
    /**
     * @return Collection<int, array{
     *     code: string, label: string, sort_order: int,
     *     reached: int, answered: int, answer_rate: float,
     *     dropped_here: int, drop_rate: float
     * }>
     */
    public function forTrade(Trade $trade): Collection
    {
        $questions = $trade->questions()->orderBy('sort_order')->orderBy('id')->get();

        if ($questions->isEmpty()) {
            return collect();
        }

        $orderByCode = $questions->pluck('sort_order', 'code');

        $items = OrderDraftItem::query()
            ->where('trade_id', $trade->id)
            ->with(['answers:id,order_draft_item_id,question_code', 'draft:id,status'])
            ->get();

        $reached = $items->count();

        // Là où chaque commande s'est arrêtée : la dernière question renseignée, pour celles qui
        // n'ont jamais abouti. Une commande confirmée n'a rien abandonné, elle a fini.
        $stoppedAt = $items
            ->filter(fn (OrderDraftItem $item) => $item->draft?->status !== OrderDraftStatus::CONVERTED)
            ->map(function (OrderDraftItem $item) use ($orderByCode) {
                return $item->answers
                    ->pluck('question_code')
                    ->filter(fn (string $code) => $orderByCode->has($code))
                    ->sortByDesc(fn (string $code) => $orderByCode[$code])
                    ->first();
            })
            ->filter()
            ->countBy();

        $answeredByCode = $items
            ->flatMap(fn (OrderDraftItem $item) => $item->answers->pluck('question_code')->unique())
            ->countBy();

        return $questions->map(function ($question) use ($reached, $answeredByCode, $stoppedAt) {
            $answered = (int) ($answeredByCode[$question->code] ?? 0);
            $dropped = (int) ($stoppedAt[$question->code] ?? 0);

            return [
                'code' => $question->code,
                'label' => $question->label,
                'sort_order' => (int) $question->sort_order,
                'reached' => $reached,
                'answered' => $answered,
                'answer_rate' => $reached > 0 ? round($answered / $reached, 3) : 0.0,
                'dropped_here' => $dropped,
                'drop_rate' => $reached > 0 ? round($dropped / $reached, 3) : 0.0,
            ];
        })->values();
    }

    /**
     * Les questions qui font décrocher au-delà du seuil.
     *
     * Le volume compte autant que le taux : un abandon sur deux commandes ne dit rien, et
     * afficher « 50 % d'abandon » dessus ferait supprimer une question parfaitement saine.
     */
    public function worstOffenders(Trade $trade, float $threshold = 0.15, int $minimumVolume = 20): Collection
    {
        return $this->forTrade($trade)
            ->filter(fn (array $row) => $row['reached'] >= $minimumVolume && $row['drop_rate'] >= $threshold)
            ->sortByDesc('drop_rate')
            ->values();
    }
}
