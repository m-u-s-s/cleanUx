<?php

namespace App\Services\Risk;

use App\Models\RiskEvaluation;
use App\Models\RiskHold;
use App\Models\RiskRule;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RiskScoringEngine (Phase Risk v2).
 *
 * Workflow :
 *   1. Charge les règles actives depuis DB (risk_rules)
 *   2. Pour chaque règle, instancie le RuleInterface enregistré dans config
 *   3. Exécute evaluate(RiskContext) → accumule les hits
 *   4. Calcule un score total (somme des score_delta des hits, multipliés par
 *      la sévérité DB si présente)
 *   5. Décide : allow / review / block selon thresholds
 *   6. Crée RiskEvaluation + si non-allow → RiskHold
 *   7. Idempotency via key
 *   8. Soft-fail (Log warning) ne casse jamais le flow business
 */
class RiskScoringEngine
{
    public function evaluate(RiskContext $context, ?string $idempotencyKey = null): RiskEvaluation
    {
        if ($idempotencyKey) {
            $existing = RiskEvaluation::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        // Bypass roles (admins) → always allow
        if ($context->user && $this->isBypassRole($context->user)) {
            return $this->persist($context, [], 0, RiskEvaluation::DECISION_ALLOW, 'Bypass role', $idempotencyKey);
        }

        if (! Config::get('risk.enabled', true)) {
            return $this->persist($context, [], 0, RiskEvaluation::DECISION_ALLOW, 'Risk engine disabled', $idempotencyKey);
        }

        $hits = $this->runRules($context);

        $score = array_sum(array_map(fn ($h) => $h->score, $hits));
        $decision = $this->decideFor($score);

        $reason = empty($hits)
            ? 'No rule triggered'
            : implode(' | ', array_map(fn ($h) => $h->reason, $hits));

        $eval = $this->persist($context, $hits, $score, $decision, $reason, $idempotencyKey);

        if ($decision !== RiskEvaluation::DECISION_ALLOW) {
            $this->createHold($eval, $context);
        }

        ActivityLogger::log('risk.evaluated', $eval, [
            'context' => $context->contextType,
            'score' => $score,
            'decision' => $decision,
            'rules_triggered' => array_map(fn ($h) => $h->code, $hits),
        ]);

        return $eval;
    }

    public function reviewHold(RiskHold $hold, \App\Models\User $reviewer, string $decision, ?string $notes = null): RiskHold
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('decision must be approved|rejected');
        }
        if ($hold->status !== RiskHold::STATUS_ACTIVE) {
            return $hold;
        }

        $hold->forceFill([
            'status' => $decision === 'approved'
                ? RiskHold::STATUS_REVIEWED_APPROVED
                : RiskHold::STATUS_REVIEWED_REJECTED,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ])->save();

        ActivityLogger::log('risk.hold_reviewed', $hold, [
            'reviewer_id' => $reviewer->id,
            'decision' => $decision,
        ]);

        return $hold->fresh();
    }

    public function cleanupExpiredHolds(): int
    {
        return RiskHold::query()
            ->where('status', RiskHold::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => RiskHold::STATUS_EXPIRED]);
    }

    /**
     * @return array<int, RiskRuleHit>
     */
    protected function runRules(RiskContext $context): array
    {
        $dbRules = RiskRule::query()->active()->get()->keyBy('code');
        $hits = [];

        foreach ((array) Config::get('risk.rules', []) as $ruleClass) {
            if (! class_exists($ruleClass)) {
                continue;
            }
            try {
                $instance = app($ruleClass);
                if (! $instance instanceof RiskRuleInterface) {
                    continue;
                }

                $dbRule = $dbRules->get($instance->code());
                if (! $dbRule) {
                    continue;
                }

                $hit = $instance->evaluate($context, $dbRule->params ?? []);
                if ($hit) {
                    // Scale hit score by severity multiplier (DB-driven)
                    $multiplier = match ($dbRule->severity) {
                        RiskRule::SEVERITY_CRITICAL => 2.0,
                        RiskRule::SEVERITY_HIGH => 1.5,
                        RiskRule::SEVERITY_LOW => 0.5,
                        default => 1.0,
                    };
                    $scaledScore = (int) round($hit->score * $multiplier);

                    $hits[] = new RiskRuleHit(
                        code: $hit->code,
                        score: $scaledScore,
                        reason: $hit->reason,
                        details: $hit->details + ['severity' => $dbRule->severity],
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('RiskScoringEngine: rule failed', [
                    'rule' => $ruleClass,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $hits;
    }

    protected function decideFor(int $score): string
    {
        $review = (int) Config::get('risk.thresholds.review', 50);
        $block = (int) Config::get('risk.thresholds.block', 100);

        if ($score >= $block) {
            return RiskEvaluation::DECISION_BLOCK;
        }
        if ($score >= $review) {
            return RiskEvaluation::DECISION_REVIEW;
        }
        return RiskEvaluation::DECISION_ALLOW;
    }

    protected function persist(
        RiskContext $context,
        array $hits,
        int $score,
        string $decision,
        string $reason,
        ?string $idempotencyKey,
    ): RiskEvaluation {
        $subject = $context->subject;
        $request = $context->request;

        return RiskEvaluation::create([
            'user_id' => $context->user?->id,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'context' => $context->contextType,
            'score' => $score,
            'decision' => $decision,
            'reason' => mb_substr($reason, 0, 500),
            'triggered_rules' => array_map(fn ($h) => [
                'code' => $h->code,
                'score' => $h->score,
                'reason' => $h->reason,
                'details' => $h->details,
            ], $hits),
            'ip_hash' => $request ? hash('sha256', (string) $request->ip()) : null,
            'user_agent_short' => $request ? mb_substr((string) $request->userAgent(), 0, 191) : null,
            'idempotency_key' => $idempotencyKey,
            'evaluated_at' => now(),
            'metadata' => $context->extra,
        ]);
    }

    protected function createHold(RiskEvaluation $evaluation, RiskContext $context): RiskHold
    {
        return RiskHold::create([
            'user_id' => $context->user?->id,
            'subject_type' => $evaluation->subject_type,
            'subject_id' => $evaluation->subject_id,
            'risk_evaluation_id' => $evaluation->id,
            'status' => RiskHold::STATUS_ACTIVE,
            'reason' => mb_substr($evaluation->reason, 0, 191),
            'expires_at' => now()->addMinutes((int) Config::get('risk.hold_duration_minutes', 120)),
        ]);
    }

    protected function isBypassRole(\App\Models\User $user): bool
    {
        $bypass = (array) Config::get('risk.bypass_roles', []);
        $role = (string) ($user->role ?? '');
        return in_array($role, $bypass, true);
    }
}
