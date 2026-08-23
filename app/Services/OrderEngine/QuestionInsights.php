<?php

namespace App\Services\OrderEngine;

use App\Models\OrderDraftItem;
use App\Models\Trade;
use App\Support\Domain\OrderDraftStatus;
use Illuminate\Support\Collection;

/** Quelle question fait décrocher les clients. */
class QuestionInsights
{
    /**
     * @return Collection<int, array{
     * code: string, label: string, sort_order: int,
     * reached: int, answered: int, answer_rate: float,
     * dropped_here: int, drop_rate: float
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

    /** Les questions qui font décrocher au-delà du seuil. */
    public function worstOffenders(Trade $trade, float $threshold = 0.15, int $minimumVolume = 20): Collection
    {
        return $this->forTrade($trade)
            ->filter(fn (array $row) => $row['reached'] >= $minimumVolume && $row['drop_rate'] >= $threshold)
            ->sortByDesc('drop_rate')
            ->values();
    }
}
