<?php

namespace App\Services\Marketing;

use App\Models\MarketingSegment;
use App\Models\MarketingSegmentMember;
use App\Services\Conditions\RuleTreeEvaluator;
use App\Services\Conditions\RuleTreeTooComplex;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** SegmentEngine — évalue la DSL d'un segment et matérialise ses membres. */
class SegmentEngine
{
    public function compute(MarketingSegment $segment): int
    {
        if (! $segment->is_active) {
            return 0;
        }

        try {
            $query = $this->buildQuery($segment->rules ?? []);
        } catch (RuleTreeTooComplex $e) {
            ActivityLogger::log('marketing.segment_rejected', $segment, ['raison' => $e->getMessage()]);

            return 0;
        }
        if (! $query) {
            return 0;
        }

        $userIds = $query->pluck('users.id')->all();

        DB::transaction(function () use ($segment, $userIds) {
            $segment->memberships()->delete();

            $now = now();
            $rows = array_map(fn ($uid) => [
                'segment_id' => $segment->id,
                'user_id' => $uid,
                'computed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $userIds);

            if (! empty($rows)) {
                MarketingSegmentMember::query()->insert($rows);
            }

            $segment->forceFill([
                'member_count' => count($userIds),
                'last_computed_at' => $now,
            ])->save();
        });

        ActivityLogger::log('marketing.segment_computed', $segment, [
            'member_count' => count($userIds),
        ]);

        return count($userIds);
    }

    public function preview(array $rules, int $limit = 25): array
    {
        try {
            $query = $this->buildQuery($rules);
        } catch (RuleTreeTooComplex) {
            return ['count' => 0, 'sample' => []];
        }
        if (! $query) {
            return ['count' => 0, 'sample' => []];
        }

        $count = (clone $query)->count('users.id');
        $sample = $query->limit($limit)->get(['users.id', 'users.email', 'users.name'])->toArray();

        return ['count' => $count, 'sample' => $sample];
    }

    /** @return Builder<Model>|null */
    protected function buildQuery(array $rules): ?Builder
    {
        // DES REGLES VIDES NE SELECTIONNENT PERSONNE, et surtout pas tout le monde : un
        // segment vide qui prendrait toute la base lui enverrait la prochaine campagne.
        if (empty($rules)) {
            return null;
        }

        $entite = app(UserSegmentDescriptor::class);
        $requete = $entite->baseQuery();

        app(RuleTreeEvaluator::class)->apply($requete, $rules, $entite);

        return $requete;
    }
}
