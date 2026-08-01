<?php

namespace App\Services\OrderEngine;

use App\Models\OrderDraft;
use App\Models\OrderDraftAnswer;
use App\Models\OrderDraftItem;
use App\Models\Question;
use App\Models\Trade;
use App\Models\User;
use App\Support\Domain\OrderDraftStatus;
use App\Support\Domain\OrderMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Le panier : le retrouver, l'écrire, le chiffrer.
 *
 * Deux lois du parcours vivent ici, et aucune n'est cosmétique.
 *
 * Le prix s'affiche AVANT qu'on demande un compte. Le panier existe donc sans client, retrouvé par
 * un jeton de session — c'est ce qui permet à un visiteur de composer sa commande, de voir son
 * prix, et de ne s'inscrire qu'au dernier écran.
 *
 * Et revenir en arrière ne perd jamais rien. Les réponses sont écrites au fil de l'eau, pas à la
 * validation : fermer l'onglet, revenir trois heures plus tard, rouvrir sur un autre appareil une
 * fois connecté — le panier est là.
 *
 * Chaque réponse est enregistrée avec un INSTANTANÉ de son libellé et du montant qu'elle a coûté.
 * C'est ce qui rend le devis explicable ligne par ligne, et opposable si la question change ou
 * disparaît six mois plus tard.
 */
class OrderDraftManager
{
    public function __construct(
        protected PricingEngine $pricing,
        protected ConditionEvaluator $conditions,
    ) {}

    /**
     * Retrouve le panier du visiteur, ou en ouvre un.
     *
     * Un compte prime toujours sur un jeton de session : quelqu'un qui vient de se connecter doit
     * retrouver ce qu'il avait commencé anonymement sur un autre appareil.
     */
    public function resumeOrCreate(?string $sessionToken, ?User $client = null, string $mode = OrderMode::SCHEDULED): OrderDraft
    {
        $existing = null;

        if ($client) {
            $existing = OrderDraft::query()->open()->where('client_id', $client->id)->latest('id')->first();
        }

        if (! $existing && $sessionToken) {
            $existing = OrderDraft::query()->open()->where('session_token', $sessionToken)->latest('id')->first();
        }

        if ($existing) {
            /*
             * Rattachement du panier anonyme au compte qui vient de se connecter. Sans ce geste,
             * l'inscription au dernier écran ferait perdre tout ce qui la précède — exactement ce
             * que le parcours promet d'éviter.
             */
            if ($client && $existing->client_id === null) {
                $existing->update(['client_id' => $client->id]);
            }

            return $existing;
        }

        return OrderDraft::create([
            'reference' => $this->uniqueReference(),
            'client_id' => $client?->id,
            'session_token' => $sessionToken ?: Str::random(48),
            'mode' => $mode,
            'status' => OrderDraftStatus::DRAFT,
            'source' => 'web',
        ]);
    }

    /** Une ligne par métier. Idempotent : rouvrir le même métier ne crée pas un doublon. */
    public function itemFor(OrderDraft $draft, Trade $trade): OrderDraftItem
    {
        $existing = $draft->items()->where('trade_id', $trade->id)->first();

        if ($existing) {
            return $existing;
        }

        return OrderDraftItem::create([
            'order_draft_id' => $draft->id,
            'trade_id' => $trade->id,
            'sequence' => (int) $draft->items()->max('sequence') + 1,
            'status' => OrderDraftStatus::DRAFT,
        ]);
    }

    /**
     * Écrit les réponses avec leurs instantanés, et le montant que chacune a coûté.
     *
     * Les réponses aux questions CACHÉES sont supprimées, jamais conservées « au cas où ». Le
     * moteur les ignore déjà pour le prix ; les laisser en base ferait diverger le devis stocké de
     * celui qu'on affiche, et c'est le stocké qui fait foi devant un litige.
     *
     * @param  Collection<int, Question>  $questions
     * @param  array<string, mixed>  $answers
     */
    public function saveAnswers(OrderDraftItem $item, Collection $questions, array $answers): OrderDraftItem
    {
        $visible = $this->conditions->visible($questions, $answers);
        $quote = $this->pricing->quoteItem($item->trade, $questions, $answers, [
            'mode' => $item->draft->mode,
        ]);

        // Le montant de chaque ligne, indexé par code : c'est ce qui rend le devis explicable.
        $impacts = collect($quote->lines)->keyBy('code');

        DB::transaction(function () use ($item, $visible, $answers, $impacts, $quote) {
            $keptCodes = [];

            foreach ($visible as $question) {
                if (! array_key_exists($question->code, $answers)) {
                    continue;
                }

                $value = $answers[$question->code];
                $unknown = is_array($value) && ($value['unknown'] ?? false) === true;
                $keptCodes[] = $question->code;

                OrderDraftAnswer::updateOrCreate(
                    ['order_draft_item_id' => $item->id, 'question_code' => $question->code],
                    [
                        'question_id' => $question->id,
                        // L'instantané : ce que le client a VU, pas ce que la base dira demain.
                        'question_label_snapshot' => $question->label,
                        'answer_value' => is_array($value) ? $value : ['value' => $value],
                        'answer_label_snapshot' => $this->describeAnswer($question, $value, $unknown),
                        'price_impact_cents' => (int) ($impacts[$question->code]['min_cents'] ?? 0),
                        'duration_impact_min' => (int) $question->duration_impact_min,
                        'is_unknown' => $unknown,
                    ],
                );
            }

            $item->answers()->whereNotIn('question_code', $keptCodes)->delete();

            $item->update([
                'estimate_min_cents' => $quote->quoteOnly ? null : $quote->minCents,
                'estimate_max_cents' => $quote->quoteOnly ? null : $quote->maxCents,
                'duration_min' => $quote->durationMin,
            ]);
        });

        return $item->fresh(['answers']);
    }

    /** Consolide la commande entière et l'enregistre sur le panier. */
    public function reprice(OrderDraft $draft): PriceBreakdown
    {
        $items = $draft->items()->with('trade')->get();

        $breakdowns = $items->map(fn (OrderDraftItem $item) => $this->pricing->quoteItem(
            $item->trade,
            $item->trade->questions()->with(['options', 'conditions'])->get(),
            $this->answersOf($item),
            ['mode' => $draft->mode],
        ))->all();

        $order = $this->pricing->quoteOrder($breakdowns, $draft->mode);

        $draft->update([
            'estimate_min_cents' => $order->minCents,
            'estimate_max_cents' => $order->maxCents,
        ]);

        return $order;
    }

    /**
     * Les réponses d'une ligne, sous la forme que le moteur lit.
     *
     * @return array<string, mixed>
     */
    public function answersOf(OrderDraftItem $item): array
    {
        return $item->answers->mapWithKeys(fn (OrderDraftAnswer $a) => [
            $a->question_code => $a->is_unknown
                ? ['unknown' => true]
                : ($a->answer_value['value'] ?? $a->answer_value),
        ])->all();
    }

    /**
     * Le libellé de la réponse, tel qu'il figurera sur le devis.
     *
     * On enregistre le LIBELLÉ, pas la valeur technique : « Murs et plafonds », pas
     * « murs_plafonds ». Un devis se lit par un humain, parfois devant un médiateur.
     */
    protected function describeAnswer(Question $question, mixed $value, bool $unknown): string
    {
        if ($unknown) {
            return 'À évaluer sur place';
        }

        if ($question->isOptionBased()) {
            $selected = collect(is_array($value) ? $value : [$value])->map(fn ($v) => (string) $v);

            $labels = $question->options
                ->whereIn('value', $selected->all())
                ->pluck('label');

            return $labels->isNotEmpty() ? $labels->implode(', ') : (string) (is_array($value) ? implode(', ', $value) : $value);
        }

        $unit = $question->validation['unit'] ?? null;

        return trim((is_array($value) ? json_encode($value) : (string) $value).' '.$unit);
    }

    /**
     * Référence lisible et unique.
     *
     * Cinq caractères sur un alphabet de 32 laissent une chance de collision non nulle : on
     * vérifie plutôt que d'espérer, et on renvoie l'échec plutôt que de boucler sans fin.
     */
    protected function uniqueReference(): string
    {
        foreach (range(1, 10) as $ignored) {
            $reference = OrderDraft::generateReference();

            if (! OrderDraft::where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        return 'CLX-'.Str::upper(Str::random(9, 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'));
    }
}
