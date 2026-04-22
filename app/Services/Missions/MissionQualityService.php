<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionClientAction;
use App\Models\MissionIncident;
use App\Models\MissionQualityReview;
use App\Models\MissionReport;
use App\Models\User;
use App\Services\Missions\MissionHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MissionQualityService
{
    public function reportIncident(Mission $mission, User $user, array $data): MissionIncident
    {
        return DB::transaction(function () use ($mission, $user, $data) {
            $incident = MissionIncident::query()->create([
                'mission_id' => $mission->id,
                'reported_by_user_id' => $user->id,
                'incident_type' => $data['incident_type'] ?? 'general',
                'severity' => $data['severity'] ?? 'medium',
                'status' => 'open',
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'client_visible' => (bool) ($data['client_visible'] ?? true),
                'reported_at' => now(),
                'meta' => $data['meta'] ?? null,
            ]);

            $this->refreshMissionQuality($mission->fresh());
            $this->generateOrRefreshReport($mission->fresh(), $user);

            app(MissionHistoryService::class)->log(
                $mission->fresh(),
                $user,
                'incident_reported',
                'Incident signalé',
                $incident->title,
                ['severity' => $incident->severity, 'incident_type' => $incident->incident_type]
            );

            return $incident;
        });
    }

    public function submitClientFinalValidation(Mission $mission, User $client, bool $satisfied, ?string $comment = null): MissionQualityReview
    {
        return DB::transaction(function () use ($mission, $client, $satisfied, $comment) {
            $review = MissionQualityReview::query()->create([
                'mission_id' => $mission->id,
                'reviewer_user_id' => $client->id,
                'reviewer_role' => 'client',
                'final_status' => $satisfied ? 'satisfied' : 'problem_reported',
                'score' => $satisfied ? 90 : 45,
                'cleanliness_score' => $satisfied ? 90 : 50,
                'punctuality_score' => $this->punctualityScore($mission),
                'behavior_score' => $satisfied ? 90 : 60,
                'comment' => $comment,
                'reviewed_at' => now(),
            ]);

            $mission->update([
                'client_final_status' => $review->final_status,
                'client_final_validated_at' => now(),
            ]);

            if (! $satisfied) {
                MissionClientAction::query()->create([
                    'mission_id' => $mission->id,
                    'client_user_id' => $client->id,
                    'action_type' => 'issue_reported',
                    'status' => 'submitted',
                    'message' => $comment,
                    'acted_at' => now(),
                ]);

                MissionIncident::query()->create([
                    'mission_id' => $mission->id,
                    'reported_by_user_id' => $client->id,
                    'incident_type' => 'client_feedback',
                    'severity' => 'medium',
                    'status' => 'open',
                    'title' => 'Problème signalé par le client',
                    'description' => $comment,
                    'client_visible' => true,
                    'reported_at' => now(),
                ]);
            }

            $this->refreshMissionQuality($mission->fresh());
            $this->generateOrRefreshReport($mission->fresh(), $client);

            app(MissionHistoryService::class)->log(
                $mission->fresh(),
                $client,
                'client_final_validation',
                $satisfied ? 'Client satisfait' : 'Client a signalé un problème',
                $comment,
                ['final_status' => $satisfied ? 'satisfied' : 'problem_reported']
            );
            
            return $review;
        });
    }

    public function refreshMissionQuality(Mission $mission): Mission
    {
        $mission->loadMissing(['checklists.items', 'incidents', 'qualityReviews']);

        $checklist = $mission->checklists->first();
        $checklistRate = (int) ($checklist?->completion_rate ?? 0);

        $incidentPenalty = $mission->incidents
            ->whereIn('status', ['open', 'in_review'])
            ->sum(function ($incident) {
                return match ($incident->severity) {
                    'critical' => 25,
                    'high' => 18,
                    'medium' => 10,
                    default => 5,
                };
            });

        $clientReview = $mission->qualityReviews
            ->where('reviewer_role', 'client')
            ->sortByDesc('id')
            ->first();

        $clientScore = match ($clientReview?->final_status) {
            'satisfied' => 30,
            'problem_reported' => 10,
            default => 20,
        };

        $punctualityScore = $this->punctualityScore($mission);

        $score = max(0, min(
            100,
            (int) round(($checklistRate * 0.5) + $clientScore + $punctualityScore - $incidentPenalty)
        ));

        $mission->update([
            'quality_score' => $score,
            'quality_status' => $score >= 85 ? 'excellent' : ($score >= 65 ? 'good' : ($score >= 45 ? 'warning' : 'critical')),
            'quality_summary' => [
                'checklist_rate' => $checklistRate,
                'client_status' => $clientReview?->final_status,
                'incident_penalty' => $incidentPenalty,
                'punctuality_score' => $punctualityScore,
            ],
        ]);

        return $mission->fresh();
    }

    public function generateOrRefreshReport(Mission $mission, ?User $generatedBy = null): MissionReport
    {
        $mission->loadMissing(['rendezVous', 'checklists.items', 'media', 'incidents', 'qualityReviews']);

        $checklist = $mission->checklists->first();
        $beforeCount = $mission->media->where('media_type', 'before_photo')->count();
        $afterCount = $mission->media->where('media_type', 'after_photo')->count();
        $incidentCount = $mission->incidents->count();
        $clientReview = $mission->qualityReviews->where('reviewer_role', 'client')->sortByDesc('id')->first();

        $report = MissionReport::query()->updateOrCreate(
            ['mission_id' => $mission->id],
            [
                'generated_by_user_id' => $generatedBy?->id,
                'report_number' => MissionReport::query()->where('mission_id', $mission->id)->value('report_number')
                    ?: 'MR-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                'status' => 'generated',
                'generated_at' => now(),
                'summary' => $this->buildSummary($mission, $incidentCount, $clientReview?->final_status),
                'checklist_completion_rate' => (int) ($checklist?->completion_rate ?? 0),
                'before_photos_count' => $beforeCount,
                'after_photos_count' => $afterCount,
                'incident_count' => $incidentCount,
                'client_validation' => $clientReview?->final_status,
                'quality_score' => $mission->quality_score,
                'report_payload' => [
                    'mission_id' => $mission->id,
                    'booking_reference' => $mission->rendezVous?->booking_reference,
                    'status' => $mission->status,
                    'quality_status' => $mission->quality_status,
                    'quality_score' => $mission->quality_score,
                    'client_validation' => $clientReview?->final_status,
                    'checklist_completion_rate' => (int) ($checklist?->completion_rate ?? 0),
                    'before_photos_count' => $beforeCount,
                    'after_photos_count' => $afterCount,
                    'incident_count' => $incidentCount,
                    'generated_at' => now()->toISOString(),
                ],
            ]
        );

        return $report;
    }

    protected function punctualityScore(Mission $mission): int
    {
        if (! $mission->planned_start_at || ! $mission->actual_start_at) {
            return 12;
        }

        $delay = max(0, $mission->actual_start_at->diffInMinutes($mission->planned_start_at, false) * -1);

        return match (true) {
            $delay <= 5 => 20,
            $delay <= 15 => 15,
            $delay <= 30 => 10,
            default => 5,
        };
    }

    protected function buildSummary(Mission $mission, int $incidentCount, ?string $clientStatus): string
    {
        $status = $clientStatus === 'problem_reported' ? 'avec réserve client' : 'satisfaisante';

        if ($incidentCount > 0) {
            return "Mission terminée avec {$incidentCount} incident(s), validation {$status}.";
        }

        return "Mission terminée sans incident, validation {$status}.";
    }
}
