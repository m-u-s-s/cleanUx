<?php

namespace App\Services\OrderEngine;

use App\Models\Question;
use App\Support\Domain\ConditionAction;
use App\Support\Domain\ConditionOperator;
use Illuminate\Support\Collection;

/**
 * Décide quelles questions le client voit, à partir de ce qu'il a déjà répondu.
 *
 * Séparé du moteur tarifaire à dessein : une question cachée ne doit PAS peser sur le prix. Sans
 * cette séparation, répondre « au pistolet » puis revenir sur « au rouleau » laisserait le
 * supplément « type de pistolet » dans le devis — un montant que le client ne pourrait rattacher
 * à aucune question visible, et qu'il contesterait à raison.
 */
class ConditionEvaluator
{
    /**
     * Les questions effectivement visibles, dans l'ordre.
     *
     * @param  Collection<int, Question>  $questions  chargées avec leurs conditions
     * @param  array<string, mixed>  $answers  indexées par code de question
     * @return Collection<int, Question>
     */
    public function visible(Collection $questions, array $answers): Collection
    {
        return $questions->filter(fn (Question $q) => $this->isVisible($q, $questions, $answers))->values();
    }

    /**
     * @param  Collection<int, Question>  $questions
     * @param  array<string, mixed>  $answers
     */
    public function isVisible(Question $question, Collection $questions, array $answers): bool
    {
        $conditions = $question->relationLoaded('conditions')
            ? $question->conditions
            : $question->conditions()->get();

        if ($conditions->isEmpty()) {
            return true;
        }

        /*
         * Une règle `show` rend la question conditionnelle : par défaut elle est CACHÉE, et il
         * faut qu'une règle la fasse apparaître. Sans ce basculement, poser une condition
         * d'affichage n'aurait aucun effet tant qu'elle n'est pas remplie.
         */
        $hasShowRule = $conditions->contains(fn ($c) => $c->action === ConditionAction::SHOW);
        $visible = ! $hasShowRule;

        foreach ($conditions as $condition) {
            $dependsOn = $questions->firstWhere('id', $condition->depends_on_question_id);
            if (! $dependsOn) {
                continue;
            }

            $met = $this->matches(
                $condition->operator,
                $answers[$dependsOn->code] ?? null,
                $condition->value,
            );

            if ($condition->action === ConditionAction::SHOW && $met) {
                $visible = true;
            }
            if ($condition->action === ConditionAction::HIDE && $met) {
                // Une règle de masquage l'emporte : c'est la plus restrictive, et une question
                // affichée par erreur coûte plus cher qu'une question manquante.
                return false;
            }
        }

        return $visible;
    }

    /**
     * La question est-elle rendue obligatoire par une condition ?
     *
     * Distinct de la visibilité : une question visible et facultative peut devenir obligatoire
     * selon une réponse précédente.
     *
     * @param  Collection<int, Question>  $questions
     * @param  array<string, mixed>  $answers
     */
    public function isRequired(Question $question, Collection $questions, array $answers): bool
    {
        if ($question->is_required) {
            return true;
        }

        $conditions = $question->relationLoaded('conditions')
            ? $question->conditions
            : $question->conditions()->get();

        foreach ($conditions as $condition) {
            if ($condition->action !== ConditionAction::REQUIRE) {
                continue;
            }

            $dependsOn = $questions->firstWhere('id', $condition->depends_on_question_id);
            if ($dependsOn && $this->matches($condition->operator, $answers[$dependsOn->code] ?? null, $condition->value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Un opérateur inconnu rend FAUX plutôt que de lever.
     *
     * Le questionnaire est de la donnée saisie en back-office : une condition mal formée doit
     * dégrader l'affichage d'une question, jamais faire tomber le parcours de commande entier.
     */
    protected function matches(string $operator, mixed $answer, mixed $expected): bool
    {
        if ($operator === ConditionOperator::IS_ANSWERED) {
            return $answer !== null && $answer !== '' && $answer !== [];
        }

        if ($answer === null) {
            return false;
        }

        // La valeur attendue est stockée en JSON : `{"value": x}` ou la valeur nue.
        $expectedValue = is_array($expected) && array_key_exists('value', $expected)
            ? $expected['value']
            : $expected;

        return match ($operator) {
            // Comparaison souple : une réponse vient d'un formulaire, donc en chaîne. Comparer
            // strictement ferait échouer `equals 3` contre la chaîne "3".
            ConditionOperator::EQUALS => $this->looselyEquals($answer, $expectedValue),
            ConditionOperator::NOT_EQUALS => ! $this->looselyEquals($answer, $expectedValue),
            ConditionOperator::IN => is_array($expectedValue)
                && collect($expectedValue)->contains(fn ($v) => $this->looselyEquals($answer, $v)),
            ConditionOperator::GT => is_numeric($answer) && is_numeric($expectedValue) && (float) $answer > (float) $expectedValue,
            ConditionOperator::LT => is_numeric($answer) && is_numeric($expectedValue) && (float) $answer < (float) $expectedValue,
            default => false,
        };
    }

    protected function looselyEquals(mixed $answer, mixed $expected): bool
    {
        if (is_array($answer)) {
            return collect($answer)->contains(fn ($v) => $this->looselyEquals($v, $expected));
        }

        if (is_bool($answer) || is_bool($expected)) {
            return filter_var($answer, FILTER_VALIDATE_BOOLEAN) === filter_var($expected, FILTER_VALIDATE_BOOLEAN);
        }

        return (string) $answer === (string) $expected;
    }
}
