<?php

namespace App\Services\OnboardingV2;

use App\Models\OnboardingJourney;
use App\Models\OnboardingProgress;
use App\Models\OnboardingStep;
use App\Models\OnboardingStepCompletion;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * OnboardingEngine — orchestre le cycle de vie d'un journey utilisateur.
 *
 *   - startFor(user, journey_code?) : init progress + completions pending pour
 *     chaque step
 *   - getCurrentStep(progress) : 1er step required incomplet respectant depends_on
 *   - markComplete(progress, step, payload, user?) : appelle validator → si OK,
 *     persiste completion + recompute progress (status, percent, current_step)
 *   - markSkip(progress, step, user, reason) : seulement si step.is_skippable
 *   - completeJourney(progress) : marque completed + hook on_complete_user_field
 */
class OnboardingEngine
{
    public function startFor(User $user, ?string $journeyCode = null): OnboardingProgress
    {
        if (! Config::get('onboarding_v2.enabled', true)) {
            throw ValidationException::withMessages(['module' => 'Onboarding v2 disabled.']);
        }

        $code = $journeyCode ?: $this->defaultJourneyCodeFor($user);
        if (! $code) {
            throw ValidationException::withMessages(['journey' => 'Aucun journey par défaut pour ce rôle.']);
        }

        $journey = OnboardingJourney::query()
            ->where('code', $code)
            ->active()
            ->first();
        if (! $journey) {
            throw ValidationException::withMessages(['journey' => "Journey '{$code}' introuvable ou inactive."]);
        }

        $progress = OnboardingProgress::query()
            ->where('user_id', $user->id)
            ->where('journey_id', $journey->id)
            ->first();
        if ($progress) {
            return $progress;
        }

        return DB::transaction(function () use ($user, $journey) {
            $progress = OnboardingProgress::create([
                'user_id' => $user->id,
                'journey_id' => $journey->id,
                'status' => OnboardingProgress::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'percent_complete' => 0,
            ]);

            // Initialize pending completions for all steps
            foreach ($journey->steps as $step) {
                OnboardingStepCompletion::create([
                    'progress_id' => $progress->id,
                    'step_id' => $step->id,
                    'status' => OnboardingStepCompletion::STATUS_PENDING,
                ]);
            }

            $this->refreshCurrentStep($progress);

            ActivityLogger::log('onboarding_v2.journey_started', $progress, [
                'user_id' => $user->id,
                'journey_code' => $journey->code,
            ]);

            return $progress->fresh();
        });
    }

    public function getCurrentStep(OnboardingProgress $progress): ?OnboardingStep
    {
        $steps = $progress->journey->steps;
        $completions = $progress->completions()->get()->keyBy('step_id');

        foreach ($steps as $step) {
            $compl = $completions->get($step->id);
            if (! $compl) {
                continue;
            }

            if (in_array($compl->status, [
                OnboardingStepCompletion::STATUS_COMPLETED,
                OnboardingStepCompletion::STATUS_SKIPPED,
            ], true)) {
                continue;
            }

            // Check depends_on : all dependencies must be completed/skipped
            if (! empty($step->depends_on)) {
                $dependenciesMet = true;
                foreach ((array) $step->depends_on as $depCode) {
                    $depStep = $steps->firstWhere('code', $depCode);
                    if (! $depStep) {
                        continue;
                    }
                    $depCompl = $completions->get($depStep->id);
                    if (! $depCompl || ! in_array($depCompl->status, [
                        OnboardingStepCompletion::STATUS_COMPLETED,
                        OnboardingStepCompletion::STATUS_SKIPPED,
                    ], true)) {
                        $dependenciesMet = false;
                        break;
                    }
                }
                if (! $dependenciesMet) {
                    continue;
                }
            }

            return $step;
        }

        return null;
    }

    public function markComplete(
        OnboardingProgress $progress,
        OnboardingStep $step,
        array $payload = [],
        ?User $actor = null,
    ): OnboardingStepCompletion {
        if ($step->journey_id !== $progress->journey_id) {
            throw ValidationException::withMessages(['step' => 'Step ne fait pas partie de ce journey.']);
        }

        if ($progress->status === OnboardingProgress::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['status' => 'Journey déjà complet.']);
        }

        $compl = OnboardingStepCompletion::query()
            ->where('progress_id', $progress->id)
            ->where('step_id', $step->id)
            ->firstOrFail();

        // Resolve validator
        $validator = $this->resolveValidator($step);
        $validation = $validator->validate($progress->user, $step, $payload);

        $compl->increment('attempt_count');

        if (! $validation->ok) {
            $compl->forceFill([
                'status' => OnboardingStepCompletion::STATUS_FAILED,
                'last_error' => json_encode($validation->errors),
                'data' => $payload,
            ])->save();

            ActivityLogger::log('onboarding_v2.step_failed', $compl, [
                'step_code' => $step->code,
                'errors' => $validation->errors,
            ]);

            throw ValidationException::withMessages($validation->errors);
        }

        $compl->forceFill([
            'status' => OnboardingStepCompletion::STATUS_COMPLETED,
            'data' => array_merge($payload, $validation->normalizedData),
            'validator_payload' => $validation->metadata,
            'completed_at' => now(),
            'completed_by_user_id' => $actor?->id ?? $progress->user_id,
            'last_error' => null,
        ])->save();

        ActivityLogger::log('onboarding_v2.step_completed', $compl->fresh(), [
            'step_code' => $step->code,
        ]);

        $this->refreshProgress($progress);

        return $compl->fresh();
    }

    public function markSkip(
        OnboardingProgress $progress,
        OnboardingStep $step,
        User $actor,
        ?string $reason = null,
    ): OnboardingStepCompletion {
        if (! $step->is_skippable && $step->required) {
            throw ValidationException::withMessages(['step' => 'Cette étape ne peut pas être skippée.']);
        }
        if (! Config::get('onboarding_v2.allow_skip_optional_steps', true)) {
            throw ValidationException::withMessages(['skip' => 'Skip désactivé par configuration.']);
        }

        $compl = OnboardingStepCompletion::query()
            ->where('progress_id', $progress->id)
            ->where('step_id', $step->id)
            ->firstOrFail();

        $compl->forceFill([
            'status' => OnboardingStepCompletion::STATUS_SKIPPED,
            'completed_at' => now(),
            'completed_by_user_id' => $actor->id,
            'metadata' => array_merge((array) $compl->metadata, ['skip_reason' => $reason]),
        ])->save();

        ActivityLogger::log('onboarding_v2.step_skipped', $compl->fresh(), [
            'step_code' => $step->code,
            'reason' => $reason,
        ]);

        $this->refreshProgress($progress);

        return $compl->fresh();
    }

    protected function refreshProgress(OnboardingProgress $progress): void
    {
        $progress->refresh();

        $completions = $progress->completions()->get();
        $steps = $progress->journey->steps;

        $totalRequired = $steps->where('required', true)->count();
        $completedRequired = 0;
        foreach ($steps as $step) {
            if (! $step->required) {
                continue;
            }
            $compl = $completions->firstWhere('step_id', $step->id);
            if ($compl && in_array($compl->status, [
                OnboardingStepCompletion::STATUS_COMPLETED,
                OnboardingStepCompletion::STATUS_SKIPPED,
            ], true)) {
                $completedRequired++;
            }
        }

        $percent = $totalRequired > 0 ? round(($completedRequired / $totalRequired) * 100, 2) : 0;

        $newStatus = $progress->status;
        $completedAt = $progress->completed_at;

        if ($completedRequired === $totalRequired && $totalRequired > 0) {
            $newStatus = OnboardingProgress::STATUS_COMPLETED;
            $completedAt ??= now();
            $this->onComplete($progress);
        } elseif ($progress->status === OnboardingProgress::STATUS_NOT_STARTED) {
            $newStatus = OnboardingProgress::STATUS_IN_PROGRESS;
        }

        $progress->forceFill([
            'status' => $newStatus,
            'completed_at' => $completedAt,
            'percent_complete' => $percent,
        ])->save();

        $this->refreshCurrentStep($progress);
    }

    protected function refreshCurrentStep(OnboardingProgress $progress): void
    {
        $current = $this->getCurrentStep($progress);
        $progress->forceFill(['current_step_code' => $current?->code])->save();
    }

    protected function onComplete(OnboardingProgress $progress): void
    {
        $column = Config::get('onboarding_v2.on_complete_user_field');
        if ($column && Schema::hasColumn('users', $column)) {
            try {
                DB::table('users')->where('id', $progress->user_id)->update([
                    $column => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('OnboardingEngine: on_complete user field update failed', [
                    'user_id' => $progress->user_id,
                    'column' => $column,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        ActivityLogger::log('onboarding_v2.journey_completed', $progress, [
            'user_id' => $progress->user_id,
            'journey_code' => $progress->journey?->code,
        ]);
    }

    protected function defaultJourneyCodeFor(User $user): ?string
    {
        $defaults = (array) Config::get('onboarding_v2.default_journey_per_role', []);
        $role = (string) ($user->role ?? '');
        return $defaults[$role] ?? null;
    }

    protected function resolveValidator(OnboardingStep $step): OnboardingStepValidator
    {
        $fqcn = $step->validator_class;
        if (! $fqcn) {
            $registry = (array) Config::get('onboarding_v2.validators', []);
            $fqcn = $registry[$step->step_type] ?? null;
        }

        if (! $fqcn || ! class_exists($fqcn)) {
            throw ValidationException::withMessages([
                'validator' => "Validator pour step_type '{$step->step_type}' introuvable.",
            ]);
        }

        $instance = app($fqcn);
        if (! $instance instanceof OnboardingStepValidator) {
            throw ValidationException::withMessages([
                'validator' => "{$fqcn} n'implémente pas OnboardingStepValidator.",
            ]);
        }
        return $instance;
    }
}
