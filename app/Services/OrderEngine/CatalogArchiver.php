<?php

namespace App\Services\OrderEngine;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Sector;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Retirer une entrée du catalogue sans détruire l'histoire.
 *
 * Une suppression est TOUJOURS un archivage. Un `DELETE` physique sur un métier ou une question
 * emporterait avec lui la lisibilité de tous les devis et de toutes les factures qui s'y
 * rattachent — des documents comptables, opposables, parfois vieux de plusieurs années. Aucune
 * interface d'administration ne doit pouvoir provoquer ça, même par accident, même en connaissant
 * le mot de passe.
 *
 * L'archivage combine deux gestes distincts, et c'est délibéré : `is_active` retire du parcours
 * client, `deleted_at` retire des écrans d'administration. Un catalogue peut ainsi être dépublié
 * sans être rangé, ou rangé sans disparaître des historiques.
 *
 * Avant d'archiver, l'interface DOIT annoncer l'impact — « ce métier est utilisé par 312
 * commandes ». Un administrateur qui découvre les conséquences après coup n'a plus de recours.
 */
class CatalogArchiver
{
    /**
     * Ce que l'archivage va toucher, et ce qu'il ne touchera pas.
     *
     * @return array{used_count: int, children_count: int, summary: string, reversible: bool}
     */
    public function impactOf(Model $entity): array
    {
        return match (true) {
            $entity instanceof Sector => $this->sectorImpact($entity),
            $entity instanceof Trade => $this->tradeImpact($entity),
            $entity instanceof Question => $this->questionImpact($entity),
            $entity instanceof QuestionOption => $this->optionImpact($entity),
            default => ['used_count' => 0, 'children_count' => 0, 'summary' => '', 'reversible' => true],
        };
    }

    /**
     * Archive : retire du catalogue, conserve l'historique.
     *
     * Transaction volontaire — désactiver sans ranger, ou l'inverse, laisserait une entrée dans un
     * état que ni le parcours client ni l'administration ne saurait présenter.
     */
    public function archive(Model $entity): Model
    {
        return DB::transaction(function () use ($entity) {
            if ($entity instanceof QuestionOption) {
                // Les options n'ont pas de suppression douce : les désactiver suffit, et les
                // réponses déjà données gardent leur libellé figé.
                $entity->update(['is_active' => false]);

                return $entity->fresh();
            }

            $entity->update(['is_active' => false]);
            $entity->delete();

            return $entity->fresh() ?? $entity;
        });
    }

    /** Remet en service ce qui avait été archivé. Rien n'a été perdu, tout se rouvre. */
    public function restore(Model $entity): Model
    {
        return DB::transaction(function () use ($entity) {
            if (method_exists($entity, 'restore')) {
                $entity->restore();
            }
            $entity->update(['is_active' => true]);

            return $entity->fresh() ?? $entity;
        });
    }

    /**
     * Archiver un secteur ne touche pas ses métiers.
     *
     * Ils restent utilisables ailleurs — devis en cours, réservations, matching prestataire — mais
     * disparaissent du carrousel faute de secteur publié. C'est précisément ce qu'un administrateur
     * doit savoir avant de cliquer.
     */
    protected function sectorImpact(Sector $sector): array
    {
        $trades = $sector->trades()->count();

        return [
            'used_count' => 0,
            'children_count' => $trades,
            'summary' => $trades === 0
                ? 'Ce secteur ne contient aucun métier. Il sera simplement retiré du carrousel.'
                : sprintf(
                    'Ce secteur contient %d métier%s. Ils resteront intacts, mais disparaîtront du carrousel tant qu’aucun autre secteur ne les accueille.',
                    $trades,
                    $trades > 1 ? 's' : '',
                ),
            'reversible' => true,
        ];
    }

    protected function tradeImpact(Trade $trade): array
    {
        $used = DB::table('order_draft_items')->where('trade_id', $trade->id)->count();
        $questions = $trade->questions()->count();

        return [
            'used_count' => $used,
            'children_count' => $questions,
            'summary' => sprintf(
                'Ce métier est utilisé par %d commande%s et porte %d question%s. Il sera masqué du catalogue ; l’historique reste intact et lisible.',
                $used,
                $used > 1 ? 's' : '',
                $questions,
                $questions > 1 ? 's' : '',
            ),
            'reversible' => true,
        ];
    }

    /**
     * Le compte se fait sur le CODE, pas sur la clé étrangère.
     *
     * Le code est la clé sous laquelle les réponses sont enregistrées : compter dessus fait porter
     * l'impact annoncé sur exactement ce que les instantanés retiennent.
     *
     * À ne PAS justifier par une détache de `question_id` à l'archivage : une suppression douce ne
     * déclenche pas la clé étrangère, l'identifiant survit. Une première version de ce commentaire
     * l'affirmait ; la mutation a montré qu'aucun test ne la démentait, donc qu'elle était fausse.
     * Sur le schéma actuel, compter par identifiant donnerait le même résultat — l'index unique
     * `(trade_id, code)` couvrant les lignes archivées, un code ne peut pas être réattribué.
     */
    protected function questionImpact(Question $question): array
    {
        $used = DB::table('order_draft_answers')
            ->join('order_draft_items', 'order_draft_items.id', '=', 'order_draft_answers.order_draft_item_id')
            ->where('order_draft_answers.question_code', $question->code)
            ->where('order_draft_items.trade_id', $question->trade_id)
            ->count();

        $dependents = DB::table('question_conditions')
            ->where('depends_on_question_id', $question->id)
            ->count();

        $summary = sprintf(
            'Cette question a été répondue %d fois. Les devis et factures existants restent lisibles : le libellé et la réponse y sont figés.',
            $used,
        );

        if ($dependents > 0) {
            // Archiver une question dont d'autres dépendent rendrait celles-ci invisibles pour
            // toujours : leur condition d'affichage ne pourrait plus jamais être remplie.
            $summary .= sprintf(
                ' Attention : %d question%s dépend%s de celle-ci et ne s’affichera%s plus.',
                $dependents,
                $dependents > 1 ? 's' : '',
                $dependents > 1 ? 'ent' : '',
                $dependents > 1 ? 'ient' : '',
            );
        }

        return [
            'used_count' => $used,
            'children_count' => $dependents,
            'summary' => $summary,
            'reversible' => true,
        ];
    }

    protected function optionImpact(QuestionOption $option): array
    {
        return [
            'used_count' => 0,
            'children_count' => 0,
            'summary' => 'Cette réponse ne sera plus proposée. Les commandes qui l’ont retenue gardent son libellé.',
            'reversible' => true,
        ];
    }
}
