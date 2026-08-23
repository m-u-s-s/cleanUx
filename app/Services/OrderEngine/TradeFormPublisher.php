<?php

namespace App\Services\OrderEngine;

use App\Models\Trade;
use App\Models\TradeFormRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Publier un questionnaire, c'est en figer une version. */
class TradeFormPublisher
{
    public function __construct(
        protected TradeFormSchema $schema,
        protected QuestionnaireValidator $validator,
    ) {}

    /**
     * Fige l'état courant et le met en ligne.
     *
     * @throws ValidationException si un défaut bloquant s'y oppose
     */
    public function publish(Trade $trade, ?User $publisher = null): TradeFormRevision
    {
        $blocking = collect($this->validator->inspect($trade))
            ->where('severity', QuestionnaireValidator::SEVERITY_ERROR);

        if ($blocking->isNotEmpty()) {
            throw ValidationException::withMessages([
                'publication' => $blocking->pluck('message')->all(),
            ]);
        }

        return DB::transaction(function () use ($trade, $publisher) {
            // Le numéro de version est calculé DANS la transaction, sur une lecture verrouillée : deux publications simultanées prendraient sinon le même numéro et l'index unique ferait échouer la seconde, sans que l'administrateur comprenne pourquoi.
            $version = (int) TradeFormRevision::query()
                ->where('trade_id', $trade->id)
                ->lockForUpdate()
                ->max('version') + 1;

            $revision = TradeFormRevision::create([
                'trade_id' => $trade->id,
                'version' => $version,
                'schema' => $this->schema->serialise($trade),
                'published_by_user_id' => $publisher?->id,
                'published_at' => now(),
            ]);

            $trade->update(['published_at' => now()]);

            return $revision;
        });
    }

    /**
     * Remet une version publiée en ligne.
     *
     * @throws ValidationException si l'état restauré porte un défaut bloquant
     */
    public function restore(TradeFormRevision $revision, ?User $publisher = null): TradeFormRevision
    {
        $trade = $revision->trade ?? Trade::findOrFail($revision->trade_id);

        app(QuestionnairePortability::class)->import($trade, $revision->schema);

        return $this->publish($trade->fresh(), $publisher);
    }

    /** La version en ligne, celle que les commandes doivent citer. */
    public function currentRevision(Trade $trade): ?TradeFormRevision
    {
        return TradeFormRevision::query()
            ->where('trade_id', $trade->id)
            ->orderByDesc('version')
            ->first();
    }

    /** Le questionnaire a-t-il changé depuis la dernière publication ? */
    public function hasUnpublishedChanges(Trade $trade): bool
    {
        $revision = $this->currentRevision($trade);

        if (! $revision) {
            return $trade->questions()->exists();
        }

        return ! $this->sameSchema($revision->schema, $this->schema->serialise($trade));
    }

    /**
     * Deux schémas décrivent-ils le même questionnaire ?
     *
     * @param  array<mixed>  $left
     * @param  array<mixed>  $right
     */
    public function sameSchema(array $left, array $right): bool
    {
        return $this->canonicalise($left) === $this->canonicalise($right);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    protected function canonicalise(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalise($item);
            }
        }

        return $value;
    }
}
