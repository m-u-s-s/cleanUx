<?php

namespace App\Services\Quality;

use App\Models\InspectionItem;
use App\Models\MissionQualityInspection;
use App\Models\QualityChecklistItem;
use Illuminate\Support\Facades\Config;

/**
 * QualityScoringEngine — calcule le score d'une inspection.
 *
 * Pour chaque InspectionItem :
 *   - Si `met=true` → score_awarded = checklist_item.weight
 *   - Sinon → 0 (sauf si rating, où on award rating/max_rating × weight)
 *
 * Score total = sum(score_awarded) / sum(weight des items required)
 * Renvoyé en pourcentage (0..100).
 */
class QualityScoringEngine
{
    public function recompute(MissionQualityInspection $inspection): MissionQualityInspection
    {
        $items = $inspection->items()->with('checklistItem')->get();

        $scoreSum = 0;
        $scoreMax = 0;

        foreach ($items as $item) {
            $checklistItem = $item->checklistItem;
            if (! $checklistItem) {
                continue;
            }

            $weight = (int) $checklistItem->weight;
            $scoreMax += $weight;

            $awarded = $this->awardFor($item, $checklistItem);
            $item->forceFill([
                'score_awarded' => $awarded,
                'met' => $awarded >= $weight,
            ])->save();

            $scoreSum += $awarded;
        }

        $inspection->forceFill([
            'score_calculated' => $scoreSum,
            'score_max' => $scoreMax,
        ])->save();

        return $inspection->fresh();
    }

    public function awardFor(InspectionItem $item, QualityChecklistItem $checklistItem): int
    {
        $weight = (int) $checklistItem->weight;
        $value = $item->value;

        switch ($checklistItem->item_type) {
            case QualityChecklistItem::TYPE_BOOLEAN:
                $ok = (bool) ($value['answer'] ?? $value ?? false);
                return $ok ? $weight : 0;

            case QualityChecklistItem::TYPE_RATING:
                $rating = (int) ($value['rating'] ?? 0);
                $max = (int) ($checklistItem->valid_options['max'] ?? 5);
                if ($max <= 0) return 0;
                return (int) round(($rating / $max) * $weight);

            case QualityChecklistItem::TYPE_PHOTO:
                $hasPhotos = (int) $item->photos_count > 0;
                return $hasPhotos ? $weight : 0;

            case QualityChecklistItem::TYPE_TEXT:
                $text = (string) ($value['text'] ?? '');
                return strlen(trim($text)) > 0 ? $weight : 0;

            case QualityChecklistItem::TYPE_SELECT:
                $selected = $value['selected'] ?? null;
                $expected = $checklistItem->expected_value['accepted'] ?? null;
                if (! $expected || ! is_array($expected)) {
                    return $selected ? $weight : 0;
                }
                return in_array($selected, $expected, true) ? $weight : 0;

            case QualityChecklistItem::TYPE_MEASUREMENT:
                $val = (float) ($value['value'] ?? 0);
                $min = $checklistItem->expected_value['min'] ?? null;
                $max = $checklistItem->expected_value['max'] ?? null;
                $ok = true;
                if ($min !== null && $val < (float) $min) $ok = false;
                if ($max !== null && $val > (float) $max) $ok = false;
                return $ok ? $weight : 0;

            default:
                return 0;
        }
    }

    public function gradeFor(MissionQualityInspection $inspection): ?string
    {
        $percent = $inspection->scorePercent();
        if ($percent === null) {
            return null;
        }
        $excellent = (float) Config::get('quality.thresholds.excellent', 90.0);
        $pass = (float) Config::get('quality.thresholds.pass', 70.0);
        return match (true) {
            $percent >= $excellent => 'excellent',
            $percent >= $pass => 'pass',
            default => 'fail',
        };
    }
}
