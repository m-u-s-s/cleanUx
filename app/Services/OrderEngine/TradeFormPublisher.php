<?php

namespace App\Services\OrderEngine;

use App\Models\Trade;
use App\Models\TradeFormRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publier un questionnaire, c'est en figer une version.
 *
 * Sans révision, la promesse « ce devis est explicable » s'arrête au premier changement de
 * questionnaire : on saurait ce que le client a répondu, plus jamais ce qu'on lui a demandé ni
 * comment son prix a été calculé. La révision est ce qui rend un devis vieux de six mois
 * REJOUABLE, pas seulement lisible.
 *
 * La publication est refusée si le validateur trouve un défaut bloquant. Ce n'est pas une
 * précaution de confort : une dépendance circulaire ou deux réponses par défaut produisent un
 * parcours dont une partie ne s'affiche jamais, ou dont l'écran dépend de l'ordre de tri. Mettre
 * ça en ligne coûte des commandes qu'on ne saura même pas avoir perdues.
 */
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
            /*
             * Le numéro de version est calculé DANS la transaction, sur une lecture verrouillée :
             * deux publications simultanées prendraient sinon le même numéro et l'index unique
             * ferait échouer la seconde, sans que l'administrateur comprenne pourquoi.
             */
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

    /** La version en ligne, celle que les commandes doivent citer. */
    public function currentRevision(Trade $trade): ?TradeFormRevision
    {
        return TradeFormRevision::query()
            ->where('trade_id', $trade->id)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Le questionnaire a-t-il changé depuis la dernière publication ?
     *
     * Comparaison sur le CONTENU, pas sur les horodatages : renommer une question puis annuler
     * laisse une trace dans `updated_at` sans rien changer au parcours, et signaler « brouillon en
     * attente » dans ce cas apprendrait à l'administrateur à ignorer l'avertissement.
     */
    public function hasUnpublishedChanges(Trade $trade): bool
    {
        $revision = $this->currentRevision($trade);

        if (! $revision) {
            return $trade->questions()->exists();
        }

        return $revision->schema !== $this->schema->serialise($trade);
    }
}
