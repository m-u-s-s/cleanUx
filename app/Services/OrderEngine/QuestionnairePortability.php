<?php

namespace App\Services\OrderEngine;

use App\Models\Question;
use App\Models\QuestionCondition;
use App\Models\QuestionOption;
use App\Models\QuestionStep;
use App\Models\Trade;
use Illuminate\Support\Facades\DB;

/**
 * Déplacer un questionnaire : d'un métier à l'autre, d'un environnement à l'autre.
 *
 * « Peinture intérieure » et « Peinture extérieure » partagent quatre-vingts pour cent de leurs
 * questions. Les ressaisir n'est pas seulement long : c'est ainsi qu'on obtient deux formulations
 * légèrement différentes de la même question, deux grilles tarifaires qui divergent, et un client
 * qui ne comprend pas pourquoi le même mur coûte deux prix.
 *
 * LE point délicat : les conditions se remappent par CODE. Copier `depends_on_question_id` tel
 * quel ferait pointer les conditions du nouveau métier vers les questions de l'ancien — elles se
 * déclencheraient alors sur des réponses données ailleurs, ce qui ne produit aucune erreur et
 * reste invisible jusqu'à ce qu'un client voie une question surgir sans raison.
 */
class QuestionnairePortability
{
    public function __construct(
        protected TradeFormSchema $schema,
    ) {}

    /** @return array<string, mixed> */
    public function export(Trade $trade): array
    {
        return $this->schema->serialise($trade);
    }

    /** Duplique le questionnaire d'un métier vers un autre. */
    public function duplicate(Trade $from, Trade $to): array
    {
        return $this->import($to, $this->export($from));
    }

    /**
     * Écrit un questionnaire sur un métier.
     *
     * Par CODE, jamais par identifiant : rejouer le même import met à jour au lieu de dupliquer,
     * ce qui permet de synchroniser deux environnements sans accumuler les doublons.
     *
     * Rien n'est SUPPRIMÉ : une question absente de l'import est laissée en place. Un import est
     * une contribution, pas une remise à zéro — et effacer silencieusement des questions déjà
     * répondues rendrait des devis inexplicables.
     *
     * @param  array<string, mixed>  $payload
     * @return array{created: int, updated: int, skipped: list<string>}
     */
    public function import(Trade $trade, array $payload): array
    {
        $questions = $payload['questions'] ?? [];
        $created = 0;
        $updated = 0;
        $skipped = [];

        DB::transaction(function () use ($trade, $payload, $questions, &$created, &$updated, &$skipped) {
            $steps = $this->importSteps($trade, $payload['steps'] ?? []);

            foreach ($questions as $data) {
                if (blank($data['code'] ?? null)) {
                    $skipped[] = '(question sans code)';

                    continue;
                }

                $existing = Question::withTrashed()
                    ->where('trade_id', $trade->id)
                    ->where('code', $data['code'])
                    ->first();

                /*
                 * Une question ARCHIVÉE n'est pas ressuscitée par un import. Son code reste
                 * réservé — c'est ce qui garde les instantanés univoques — mais la réécrire lui
                 * donnerait un sens neuf sous une clé déjà employée par d'anciennes réponses.
                 */
                if ($existing && $existing->trashed()) {
                    $skipped[] = $data['code'];

                    continue;
                }

                $attributes = [
                    'label' => $data['label'] ?? $data['code'],
                    'help_text' => $data['help_text'] ?? null,
                    'placeholder' => $data['placeholder'] ?? null,
                    'type' => $data['type'] ?? 'text',
                    'is_required' => (bool) ($data['is_required'] ?? false),
                    'allows_unknown' => (bool) ($data['allows_unknown'] ?? true),
                    'is_essential' => (bool) ($data['is_essential'] ?? false),
                    'is_active' => (bool) ($data['is_active'] ?? true),
                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                    'step_id' => $steps[$data['step_title'] ?? null] ?? null,
                    'default_value' => $data['default_value'] ?? null,
                    'validation' => $data['validation'] ?? null,
                    'pricing' => $data['pricing'] ?? null,
                    'display' => $data['display'] ?? null,
                    'duration_impact_min' => (int) ($data['duration_impact_min'] ?? 0),
                ];

                if ($existing) {
                    $existing->update($attributes);
                    $question = $existing;
                    $updated++;
                } else {
                    $question = Question::create($attributes + [
                        'trade_id' => $trade->id,
                        'code' => $data['code'],
                    ]);
                    $created++;
                }

                $this->importOptions($question, $data['options'] ?? []);
            }

            // Les conditions se posent APRÈS toutes les questions : celle dont on dépend peut
            // arriver plus loin dans le fichier.
            $this->importConditions($trade, $questions);
        });

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return array<string, int> titre → identifiant
     */
    protected function importSteps(Trade $trade, array $steps): array
    {
        $map = [];

        foreach ($steps as $step) {
            if (blank($step['title'] ?? null)) {
                continue;
            }

            $map[$step['title']] = QuestionStep::updateOrCreate(
                ['trade_id' => $trade->id, 'title' => $step['title']],
                ['subtitle' => $step['subtitle'] ?? null, 'sort_order' => (int) ($step['sort_order'] ?? 0)],
            )->id;
        }

        return $map;
    }

    /** @param  list<array<string, mixed>>  $options */
    protected function importOptions(Question $question, array $options): void
    {
        foreach ($options as $option) {
            if (blank($option['value'] ?? null)) {
                continue;
            }

            QuestionOption::updateOrCreate(
                ['question_id' => $question->id, 'value' => $option['value']],
                [
                    'label' => $option['label'] ?? $option['value'],
                    'description' => $option['description'] ?? null,
                    'icon' => $option['icon'] ?? null,
                    'price_modifier_cents' => (int) ($option['price_modifier_cents'] ?? 0),
                    'price_multiplier' => $option['price_multiplier'] ?? null,
                    'duration_modifier_min' => (int) ($option['duration_modifier_min'] ?? 0),
                    'sort_order' => (int) ($option['sort_order'] ?? 0),
                    'is_default' => (bool) ($option['is_default'] ?? false),
                    'is_active' => (bool) ($option['is_active'] ?? true),
                ],
            );
        }
    }

    /**
     * Les conditions, remappées par code.
     *
     * @param  list<array<string, mixed>>  $questions
     */
    protected function importConditions(Trade $trade, array $questions): void
    {
        $idsByCode = Question::where('trade_id', $trade->id)->pluck('id', 'code');

        foreach ($questions as $data) {
            $questionId = $idsByCode[$data['code'] ?? ''] ?? null;

            if (! $questionId) {
                continue;
            }

            foreach ($data['conditions'] ?? [] as $condition) {
                $dependsOnId = $idsByCode[$condition['depends_on_code'] ?? ''] ?? null;

                // Une condition dont la référence n'existe pas dans CE métier est écartée : la
                // poser rendrait sa question invisible pour toujours, sans rien signaler.
                if (! $dependsOnId || $dependsOnId === $questionId) {
                    continue;
                }

                QuestionCondition::updateOrCreate(
                    [
                        'question_id' => $questionId,
                        'depends_on_question_id' => $dependsOnId,
                        'action' => $condition['action'] ?? 'show',
                    ],
                    [
                        'operator' => $condition['operator'] ?? 'equals',
                        'value' => $condition['value'] ?? null,
                    ],
                );
            }
        }
    }
}
