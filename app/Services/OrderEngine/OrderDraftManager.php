<?php

namespace App\Services\OrderEngine;

use App\Models\OrderDraft;
use App\Models\OrderDraftAnswer;
use App\Models\OrderDraftItem;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Trade;
use App\Models\User;
use App\Support\Domain\OrderDraftStatus;
use App\Support\Domain\OrderMode;
use App\Support\HumanReference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Le panier : le retrouver, l'écrire, le chiffrer. */
class OrderDraftManager
{
    public function __construct(
        protected PricingEngine $pricing,
        protected ConditionEvaluator $conditions,
    ) {}

    /** Retrouve le panier du visiteur, ou en ouvre un. */
    /** Combien de temps une clé de rattrapage reste utilisable. */
    private const RECOVERY_LIFETIME_DAYS = 14;

    /** La dernière clé émise, en clair — elle n'existe qu'ici, jamais en base. */
    private ?string $lastIssuedKey = null;

    /** Émet une clé de rattrapage pour ce panier, et rend sa forme en CLAIR. */
    public function issueRecoveryKey(OrderDraft $draft): string
    {
        $key = Str::random(64);

        $draft->update([
            'recovery_key_hash' => hash('sha256', $key),
            'recovery_key_expires_at' => now()->addDays(self::RECOVERY_LIFETIME_DAYS),
        ]);

        return $this->lastIssuedKey = $key;
    }

    /** Retrouve un panier depuis une clé, ET FAIT TOURNER LA CLÉ. */
    public function recoverByKey(?string $key): ?OrderDraft
    {
        if (blank($key)) {
            return null;
        }

        $draft = OrderDraft::query()
            ->open()
            ->where('recovery_key_hash', hash('sha256', $key))
            ->where('recovery_key_expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $draft) {
            return null;
        }

        $this->issueRecoveryKey($draft);

        return $draft->fresh();
    }

    /** La clé émise lors du dernier appel, à remettre au navigateur. */
    public function lastIssuedKey(): ?string
    {
        return $this->lastIssuedKey;
    }

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
            // Rattachement du panier anonyme au compte qui vient de se connecter.
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
            // La révision employée est retenue avec la commande.
            'trade_form_revision_id' => app(TradeFormPublisher::class)->currentRevision($trade)?->id,
        ]);
    }

    /**
     * Écrit les réponses avec leurs instantanés, et le montant que chacune a coûté.
     *
     * @param  Collection<int, Question>  $questions
     * @param  array<string, mixed>  $answers
     */
    public function saveAnswers(OrderDraftItem $item, Collection $questions, array $answers): OrderDraftItem
    {
        $visible = $this->conditions->visible($questions, $answers);
        // La grille de la zone entre AUSSI ici : les impacts écrits ligne par ligne servent à
        // expliquer le devis, et un devis expliqué avec un tarif national alors qu'on facture le
        // tarif bruxellois est indéfendable devant un litige.
        $quote = $this->pricing->quoteItem($item->trade, $questions, $answers, [
            'mode' => $item->draft->mode,
        ] + app(ZonePricingResolver::class)->pricingContext(
            (int) $item->trade_id,
            $item->draft->service_zone_id ? (int) $item->draft->service_zone_id : null,
            // Le panier porte la ROUTE : sans lui, un tarif au kilometre n'aurait rien a multiplier.
            $item->draft,
        ));

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
                        // Le libellé TEL QUE LE CLIENT L'A VU, donc dans SA langue. Enregistrer
                        // la version française d'une question lue en néerlandais rendrait le devis
                        // inopposable : ce n'est pas ce à quoi il a répondu.
                        'question_label_snapshot' => $question->translate('label'),
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

        $resolver = app(ZonePricingResolver::class);
        $zoneId = $draft->service_zone_id ? (int) $draft->service_zone_id : null;

        $breakdowns = $items->map(fn (OrderDraftItem $item) => $this->pricing->quoteItem(
            $item->trade,
            $item->trade->questions()->with(['options.translations', 'conditions', 'translations'])->get(),
            $this->answersOf($item),
            ['mode' => $draft->mode] + $resolver->pricingContext((int) $item->trade_id, $zoneId, $draft),
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

    /** Le libellé de la réponse, tel qu'il figurera sur le devis. */
    protected function describeAnswer(Question $question, mixed $value, bool $unknown): string
    {
        if ($unknown) {
            return 'À évaluer sur place';
        }

        if ($question->isOptionBased()) {
            $selected = collect(is_array($value) ? $value : [$value])->map(fn ($v) => (string) $v);

            $labels = $question->options
                ->whereIn('value', $selected->all())
                ->map(fn (QuestionOption $option) => $option->translate('label'));

            return $labels->isNotEmpty() ? $labels->implode(', ') : (string) (is_array($value) ? implode(', ', $value) : $value);
        }

        // Une localisation se lit par son ADRESSE, pas par ses coordonnées.
        if ($question->isLocation() && is_array($value)) {
            return (string) ($value['label'] ?? '');
        }

        $unit = $question->validation['unit'] ?? null;

        return trim((is_array($value) ? json_encode($value) : (string) $value).' '.$unit);
    }

    /** Référence lisible et unique. */
    protected function uniqueReference(): string
    {
        foreach (range(1, 10) as $ignored) {
            $reference = OrderDraft::generateReference();

            if (! OrderDraft::where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        return HumanReference::prefixed('CLX-', 9);
    }
}
