<?php

namespace App\Services\OrderEngine;

use App\Models\Trade;

/**
 * Le questionnaire d'un métier, figé sous forme de données.
 *
 * Sert trois usages qui doivent tous produire EXACTEMENT la même chose : la révision publiée,
 * l'export JSON, et la duplication vers un autre métier. Trois sérialisations distinctes
 * divergeraient, et c'est la révision qui en pâtirait — celle qu'on relit six mois plus tard pour
 * expliquer une facture contestée.
 *
 * Les conditions référencent les questions par leur CODE, jamais par leur identifiant. Un
 * identifiant ne survit ni à un export vers un autre environnement, ni à une duplication : les
 * conditions pointeraient alors vers les questions du métier d'origine, et se déclencheraient sur
 * les réponses de quelqu'un d'autre.
 */
class TradeFormSchema
{
    /** Version du format lui-même — pour qu'un import sache ce qu'il lit. */
    public const FORMAT_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function serialise(Trade $trade): array
    {
        $trade->loadMissing(['questionSteps', 'questions.options', 'questions.conditions']);

        // Table de correspondance identifiant → code, pour rendre les conditions portables.
        $codesById = $trade->questions->pluck('code', 'id');

        return [
            'format_version' => self::FORMAT_VERSION,
            'trade' => [
                'slug' => $trade->slug,
                'name' => $trade->name,
                'base_price_cents' => $trade->base_price_cents,
                'pricing_unit' => $trade->pricing_unit,
                'estimated_duration_min' => $trade->estimated_duration_min,
                'min_duration_min' => $trade->min_duration_min,
                'allows_scheduled' => (bool) $trade->allows_scheduled,
                'allows_asap' => (bool) $trade->allows_asap,
                'allows_bundle' => (bool) $trade->allows_bundle,
            ],
            'steps' => $trade->questionSteps
                ->map(fn ($step) => [
                    'title' => $step->title,
                    'subtitle' => $step->subtitle,
                    'sort_order' => $step->sort_order,
                ])
                ->values()
                ->all(),
            'questions' => $trade->questions
                ->sortBy('sort_order')
                ->map(fn ($question) => [
                    'code' => $question->code,
                    'label' => $question->label,
                    'help_text' => $question->help_text,
                    'placeholder' => $question->placeholder,
                    'type' => $question->type,
                    'is_required' => (bool) $question->is_required,
                    'allows_unknown' => (bool) $question->allows_unknown,
                    'is_essential' => (bool) $question->is_essential,
                    'is_active' => (bool) $question->is_active,
                    'sort_order' => $question->sort_order,
                    'step_title' => $question->step?->title,
                    'default_value' => $question->default_value,
                    'validation' => $question->validation,
                    'pricing' => $question->pricing,
                    'display' => $question->display,
                    'duration_impact_min' => $question->duration_impact_min,
                    'options' => $question->options
                        ->map(fn ($option) => [
                            'label' => $option->label,
                            'description' => $option->description,
                            'icon' => $option->icon,
                            'value' => $option->value,
                            'price_modifier_cents' => (int) $option->price_modifier_cents,
                            'price_multiplier' => $option->price_multiplier,
                            'duration_modifier_min' => (int) $option->duration_modifier_min,
                            'sort_order' => $option->sort_order,
                            'is_default' => (bool) $option->is_default,
                            'is_active' => (bool) $option->is_active,
                        ])
                        ->values()
                        ->all(),
                    'conditions' => $question->conditions
                        // Une condition dont la question de référence a disparu n'est pas
                        // exportable : la réécrire vers un code inconnu produirait un
                        // questionnaire dont une partie ne s'afficherait jamais.
                        ->filter(fn ($condition) => $codesById->has($condition->depends_on_question_id))
                        ->map(fn ($condition) => [
                            'depends_on_code' => $codesById[$condition->depends_on_question_id],
                            'operator' => $condition->operator,
                            'value' => $condition->value,
                            'action' => $condition->action,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }
}
