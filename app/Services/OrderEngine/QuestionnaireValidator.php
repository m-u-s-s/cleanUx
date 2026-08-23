<?php

namespace App\Services\OrderEngine;

use App\Models\Question;
use App\Models\QuestionCondition;
use App\Models\Trade;
use App\Support\Domain\ConditionAction;
use App\Support\Domain\QuestionType;
use Illuminate\Support\Facades\Config;

/** Les garde-fous du constructeur de parcours. */
class QuestionnaireValidator
{
    /** Rompt le parcours : la publication doit être refusée. */
    public const SEVERITY_ERROR = 'error';

    /** Le parcours fonctionne mais fera perdre des clients : on avertit, on laisse publier. */
    public const SEVERITY_WARNING = 'warning';

    /**
     * @return list<array{severity: string, code: string, message: string, question_code: string|null}>
     */
    public function inspect(Trade $trade): array
    {
        $questions = $trade->questions()->with(['options.translations', 'conditions', 'translations'])->orderBy('sort_order')->get();

        return array_merge(
            $this->checkLength($trade, $questions),
            $this->checkWayOut($questions),
            $this->checkDefaults($questions),
            $this->checkCircularDependencies($questions),
            $this->checkDanglingConditions($questions),
        );
    }

    /** Rien ne s'oppose à la publication ? */
    public function canPublish(Trade $trade): bool
    {
        foreach ($this->inspect($trade) as $issue) {
            if ($issue['severity'] === self::SEVERITY_ERROR) {
                return false;
            }
        }

        return true;
    }

    /** Loi 3 — cinq à sept questions, pas quinze. Le seuil n'est pas technique. */
    protected function checkLength(Trade $trade, $questions): array
    {
        $issues = [];
        $perStep = (int) Config::get('order_engine.max_questions_per_step', 7);
        $total = (int) Config::get('order_engine.max_questions_warning', 10);

        $byStep = $questions->groupBy('step_id');

        foreach ($byStep as $stepId => $stepQuestions) {
            if ($stepQuestions->count() > $perStep) {
                $issues[] = $this->issue(
                    self::SEVERITY_WARNING,
                    'step_too_long',
                    sprintf(
                        'Une étape pose %d questions. Au-delà de %d, découpez en deux étapes avec une progression honnête.',
                        $stepQuestions->count(),
                        $perStep,
                    ),
                );
            }
        }

        if ($questions->count() > $total) {
            $issues[] = $this->issue(
                self::SEVERITY_WARNING,
                'trade_too_long',
                sprintf(
                    'Ce parcours compte %d questions. Il risque de faire abandonner 1 client sur 3.',
                    $questions->count(),
                ),
            );
        }

        return $issues;
    }

    /** Loi 6 — une question sans échappatoire est un mur. */
    protected function checkWayOut($questions): array
    {
        $issues = [];

        foreach ($questions as $question) {
            // Une photo, une adresse et une localisation n'ont pas de « je ne sais pas » : la
            // première est facultative par nature, les deux autres sont indispensables pour trouver
            // un prestataire — et, sur un trajet, pour savoir où l'emmener.
            if (in_array($question->type, [QuestionType::PHOTO, QuestionType::ADDRESS, QuestionType::LOCATION], true)) {
                continue;
            }

            if (! $question->allows_unknown) {
                $issues[] = $this->issue(
                    self::SEVERITY_WARNING,
                    'no_way_out',
                    sprintf('« %s » n’offre aucune porte de sortie : un client qui ne sait pas répondre est bloqué.', $question->label),
                    $question->code,
                );
            }
        }

        return $issues;
    }

    /** Loi 5 — un défaut, et un seul. Zéro défaut fait remplir au lieu de valider. */
    protected function checkDefaults($questions): array
    {
        $issues = [];

        foreach ($questions as $question) {
            if (! in_array($question->type, QuestionType::optionBased(), true)) {
                continue;
            }

            $defaults = $question->options->where('is_default', true)->count();

            if ($defaults === 0) {
                $issues[] = $this->issue(
                    self::SEVERITY_WARNING,
                    'no_default',
                    sprintf('« %s » n’a pas de réponse par défaut : le client devra remplir au lieu de valider.', $question->label),
                    $question->code,
                );
            }

            if ($defaults > 1) {
                $issues[] = $this->issue(
                    self::SEVERITY_ERROR,
                    'multiple_defaults',
                    sprintf('« %s » a %d réponses par défaut : l’écran dépendrait de l’ordre de tri.', $question->label, $defaults),
                    $question->code,
                );
            }
        }

        return $issues;
    }

    /** Détection des dépendances circulaires. */
    protected function checkCircularDependencies($questions): array
    {
        $graph = [];
        foreach ($questions as $question) {
            $graph[$question->id] = $question->conditions->pluck('depends_on_question_id')->all();
        }

        $issues = [];
        $state = []; // 0 = non vu, 1 = en cours d'exploration, 2 = terminé

        $visit = function (int $node) use (&$visit, &$state, $graph): bool {
            if (($state[$node] ?? 0) === 1) {
                return true; // On retombe sur un nœud de la pile courante : cycle.
            }
            if (($state[$node] ?? 0) === 2) {
                return false;
            }

            $state[$node] = 1;
            foreach ($graph[$node] ?? [] as $next) {
                if (isset($graph[$next]) && $visit($next)) {
                    return true;
                }
            }
            $state[$node] = 2;

            return false;
        };

        foreach ($questions as $question) {
            if (($state[$question->id] ?? 0) === 0 && $visit($question->id)) {
                $issues[] = $this->issue(
                    self::SEVERITY_ERROR,
                    'circular_dependency',
                    sprintf('« %s » fait partie d’une dépendance circulaire : ces questions ne s’afficheront jamais.', $question->label),
                    $question->code,
                );
            }
        }

        return $issues;
    }

    /** Une condition qui pointe vers une question archivée ou d'un autre métier. */
    protected function checkDanglingConditions($questions): array
    {
        $ids = $questions->pluck('id')->all();
        $issues = [];

        foreach ($questions as $question) {
            foreach ($question->conditions as $condition) {
                if ($condition->action !== ConditionAction::SHOW) {
                    continue;
                }

                if (! in_array($condition->depends_on_question_id, $ids, true)) {
                    $issues[] = $this->issue(
                        self::SEVERITY_ERROR,
                        'dangling_condition',
                        sprintf('« %s » dépend d’une question qui n’existe plus : elle ne s’affichera jamais.', $question->label),
                        $question->code,
                    );
                }
            }
        }

        return $issues;
    }

    /** @return array{severity: string, code: string, message: string, question_code: string|null} */
    protected function issue(string $severity, string $code, string $message, ?string $questionCode = null): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'question_code' => $questionCode,
        ];
    }

    /** @param  QuestionCondition  $condition */
    public function describeCondition($condition, ?Question $dependsOn): string
    {
        $operator = match ($condition->operator) {
            'equals' => 'est',
            'not_equals' => 'n’est pas',
            'in' => 'fait partie de',
            'gt' => 'est supérieur à',
            'lt' => 'est inférieur à',
            'is_answered' => 'a été répondue',
            default => $condition->operator,
        };

        $action = match ($condition->action) {
            ConditionAction::HIDE => 'Masquer',
            ConditionAction::REQUIRE => 'Rendre obligatoire',
            default => 'Afficher',
        };

        $value = $condition->value['value'] ?? null;

        return trim(sprintf(
            '%s cette question SI « %s » %s %s',
            $action,
            $dependsOn?->label ?? 'une question supprimée',
            $operator,
            is_array($value) ? implode(', ', $value) : (string) $value,
        ));
    }
}
