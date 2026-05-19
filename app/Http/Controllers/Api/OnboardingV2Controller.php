<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnboardingProgress;
use App\Models\OnboardingStep;
use App\Services\OnboardingV2\OnboardingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OnboardingV2Controller extends Controller
{
    public function __construct(protected OnboardingEngine $engine)
    {
    }

    public function me(Request $request): JsonResponse
    {
        try {
            $progress = $this->engine->startFor($request->user());
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        $progress->load(['journey.steps', 'completions']);
        $currentStep = $this->engine->getCurrentStep($progress);

        return response()->json([
            'progress' => [
                'id' => $progress->id,
                'status' => $progress->status,
                'percent_complete' => (float) $progress->percent_complete,
                'current_step_code' => $progress->current_step_code,
                'started_at' => $progress->started_at,
                'completed_at' => $progress->completed_at,
            ],
            'journey' => [
                'code' => $progress->journey->code,
                'name' => $progress->journey->name,
                'role' => $progress->journey->role,
            ],
            'current_step' => $currentStep,
            'steps' => $progress->journey->steps->map(function ($step) use ($progress) {
                $compl = $progress->completions->firstWhere('step_id', $step->id);
                return [
                    'id' => $step->id,
                    'position' => $step->position,
                    'code' => $step->code,
                    'label' => $step->label,
                    'description' => $step->description,
                    'step_type' => $step->step_type,
                    'required' => (bool) $step->required,
                    'is_skippable' => (bool) $step->is_skippable,
                    'depends_on' => $step->depends_on,
                    'completion_status' => $compl?->status,
                    'completed_at' => $compl?->completed_at,
                ];
            }),
        ]);
    }

    public function completeStep(Request $request, OnboardingStep $step): JsonResponse
    {
        $progress = $this->resolveProgressForUser($request, $step);

        $data = $request->validate([
            'payload' => ['nullable', 'array'],
        ]);

        try {
            $compl = $this->engine->markComplete(
                progress: $progress,
                step: $step,
                payload: (array) ($data['payload'] ?? []),
                actor: $request->user(),
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'ok' => true,
            'completion' => $compl,
            'progress' => $progress->fresh(),
        ]);
    }

    public function skipStep(Request $request, OnboardingStep $step): JsonResponse
    {
        $progress = $this->resolveProgressForUser($request, $step);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $compl = $this->engine->markSkip(
                progress: $progress,
                step: $step,
                actor: $request->user(),
                reason: $data['reason'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'ok' => true,
            'completion' => $compl,
            'progress' => $progress->fresh(),
        ]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $rows = OnboardingProgress::query()
            ->with(['user:id,email,name', 'journey:id,code,name,role'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('role'), fn ($q) => $q->whereHas('journey', fn ($j) => $j->where('role', $request->string('role'))))
            ->orderByDesc('updated_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();

        return response()->json(['data' => $rows]);
    }

    protected function resolveProgressForUser(Request $request, OnboardingStep $step): OnboardingProgress
    {
        $progress = OnboardingProgress::query()
            ->where('user_id', $request->user()->id)
            ->where('journey_id', $step->journey_id)
            ->first();

        if (! $progress) {
            $progress = $this->engine->startFor($request->user(), $step->journey->code);
        }
        return $progress;
    }
}
