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

    /**
     * Remet une version publiée en ligne.
     *
     * Restaurer AVANCE l'historique : la version 1 rejouée devient la version 3. Écraser ou
     * supprimer la version 2 ferait disparaître le contrat de prix sous lequel de vraies commandes
     * ont été passées — et ces commandes citent son identifiant dans `order_draft_items`. Un
     * historique dans lequel on peut effacer une ligne n'est plus opposable.
     *
     * LIMITE ASSUMÉE : une question créée APRÈS la version restaurée reste en place. `import()`
     * n'efface rien, par construction, et c'est ce qu'il faut ici : son code est peut-être déjà
     * cité par des réponses enregistrées, et la supprimer rendrait ces devis-là inexplicables. La
     * restauration ramène ce que la version décrivait ; elle ne prétend pas remonter le temps.
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

        return ! $this->sameSchema($revision->schema, $this->schema->serialise($trade));
    }

    /**
     * Deux schémas décrivent-ils le même questionnaire ?
     *
     * MYSQL RÉORDONNE LES CLÉS d'une colonne JSON — il les range par longueur puis par ordre
     * alphabétique. Ce qu'on relit n'a donc pas l'ordre de ce qu'on a écrit, alors que le contenu
     * est identique. Or `!==` sur des tableaux PHP compare AUSSI l'ordre des clés : la comparaison
     * directe déclarait le questionnaire modifié à chaque appel.
     *
     * Conséquence en production, invisible sur SQLite qui conserve le texte tel quel : le
     * constructeur affichait « modifications non publiées » en permanence, y compris à la seconde
     * qui suit une publication. Un avertissement toujours allumé n'avertit plus de rien —
     * l'administrateur apprend à l'ignorer, puis rate la vraie modification.
     *
     * On compare donc sur une forme CANONIQUE, clés triées à tous les niveaux. Le tri ne touche pas
     * aux listes : leurs clés sont déjà 0, 1, 2… et l'ordre des questions reste significatif.
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
