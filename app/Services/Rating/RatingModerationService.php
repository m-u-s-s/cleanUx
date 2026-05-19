<?php

namespace App\Services\Rating;

use App\Events\Rating\RatingHidden;
use App\Events\Rating\RatingReported;
use App\Models\Feedback;
use App\Models\RatingReport;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RatingModerationService
{
    public const AUTO_HIDE_REPORTS_THRESHOLD = 3;

    public function __construct(protected RatingAggregationService $aggregator)
    {
    }

    public function report(Feedback $feedback, User $reporter, string $reason, ?string $details = null): RatingReport
    {
        if (! in_array($reason, [
            RatingReport::REASON_SPAM,
            RatingReport::REASON_OFFENSIVE,
            RatingReport::REASON_FAKE,
            RatingReport::REASON_IRRELEVANT,
            RatingReport::REASON_PERSONAL_INFO,
            RatingReport::REASON_HARASSMENT,
            RatingReport::REASON_OTHER,
        ], true)) {
            throw ValidationException::withMessages(['reason' => 'Motif de signalement invalide.']);
        }

        return DB::transaction(function () use ($feedback, $reporter, $reason, $details) {
            $existing = RatingReport::query()
                ->where('feedback_id', $feedback->id)
                ->where('reporter_user_id', $reporter->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $report = RatingReport::create([
                'feedback_id' => $feedback->id,
                'reporter_user_id' => $reporter->id,
                'reason' => $reason,
                'details' => $details,
                'status' => RatingReport::STATUS_PENDING,
            ]);

            $feedback->increment('reports_count');
            $feedback->refresh();

            ActivityLogger::log('rating.reported', $feedback, [
                'report_id' => $report->id,
                'reporter_user_id' => $reporter->id,
                'reason' => $reason,
            ]);

            RatingReported::dispatch($report);

            if ($feedback->reports_count >= self::AUTO_HIDE_REPORTS_THRESHOLD && ! $feedback->is_hidden) {
                $this->hide($feedback, null, 'auto_hidden_after_'.self::AUTO_HIDE_REPORTS_THRESHOLD.'_reports');
            }

            return $report;
        });
    }

    public function hide(Feedback $feedback, ?User $admin, string $reason): void
    {
        if ($feedback->is_hidden) {
            return;
        }

        DB::transaction(function () use ($feedback, $admin, $reason) {
            $feedback->update([
                'is_hidden' => true,
                'hidden_reason' => $reason,
                'hidden_at' => now(),
                'hidden_by_user_id' => $admin?->id,
                'status' => Feedback::STATUS_HIDDEN,
            ]);

            if ($feedback->isClientToProvider() && $feedback->employe_id) {
                $this->aggregator->recalculateForProvider((int) $feedback->employe_id);
            }

            ActivityLogger::log('rating.hidden', $feedback, [
                'admin_user_id' => $admin?->id,
                'reason' => $reason,
            ]);

            RatingHidden::dispatch($feedback);
        });
    }

    public function restore(Feedback $feedback, ?User $admin = null): void
    {
        if (! $feedback->is_hidden) {
            return;
        }

        DB::transaction(function () use ($feedback, $admin) {
            $feedback->update([
                'is_hidden' => false,
                'hidden_reason' => null,
                'hidden_at' => null,
                'hidden_by_user_id' => null,
                'status' => $feedback->published_at ? Feedback::STATUS_PUBLISHED : Feedback::STATUS_PENDING,
            ]);

            if ($feedback->isClientToProvider() && $feedback->employe_id) {
                $this->aggregator->recalculateForProvider((int) $feedback->employe_id);
            }

            ActivityLogger::log('rating.restored', $feedback, [
                'admin_user_id' => $admin?->id,
            ]);
        });
    }

    public function resolveReports(Feedback $feedback, User $admin, bool $keep): int
    {
        $status = $keep
            ? RatingReport::STATUS_REVIEWED_KEPT
            : RatingReport::STATUS_REVIEWED_HIDDEN;

        $affected = RatingReport::query()
            ->where('feedback_id', $feedback->id)
            ->where('status', RatingReport::STATUS_PENDING)
            ->update([
                'status' => $status,
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
            ]);

        if (! $keep) {
            $this->hide($feedback, $admin, 'admin_hidden_after_review');
        }

        return $affected;
    }
}
