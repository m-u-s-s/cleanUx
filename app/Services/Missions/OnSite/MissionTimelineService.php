<?php

namespace App\Services\Missions\OnSite;

use App\Models\InspectionItem;
use App\Models\Mission;
use App\Models\MissionChecklistItem;
use App\Models\MissionEvent;
use App\Services\Missions\HourlyExtensionService;
use App\Services\Missions\HourlyMissionClock;
use App\Services\Missions\MissionChecklistService;
use Illuminate\Support\Carbon;

/** LA MISSION VUE DEPUIS LE SALON DU CLIENT — arrivé, démarré, ce qui est fait, quand ça finit. */
class MissionTimelineService
{
    public function __construct(
        protected MissionMediaService $mediaService,
        protected MissionIncidentService $incidentService,
        protected HourlyMissionClock $horloge,
        protected HourlyExtensionService $extensions,
    ) {}

    /**
     * Le fil complet d'une mission, du plus ancien au plus récent.
     *
     * @return array{
     * mission_id: int,
     * status: string|null,
     * started_at: string|null,
     * estimated_end_at: string|null,
     * clock: array<string, mixed>,
     * extension: array<string, mixed>|null,
     * progress: array{done: int, total: int, percent: int},
     * entries: list<array<string, mixed>>
     * }
     */
    public function pour(Mission $mission, bool $vueClient = true): array
    {
        $entrees = collect()
            ->concat($this->depuisLesEvenements($mission))
            ->concat($this->depuisLesItemsDInspection($mission))
            ->concat($this->depuisLesIncidents($mission, $vueClient))
            ->concat($this->depuisLesMedias($mission, $vueClient))
            ->filter(fn (array $e) => $e['at'] !== null)
            ->sortBy('at')
            ->values()
            ->all();

        $avancement = $this->avancement($mission);

        return [
            'mission_id' => $mission->id,
            'status' => $mission->status,
            'started_at' => $mission->actual_start_at?->toIso8601String(),
            'estimated_end_at' => $this->finEstimee($mission)?->toIso8601String(),
            // L'HEURE ESTIMÉE ET LE TEMPS ACHETÉ SONT DEUX NOTIONS DISTINCTES, et ce fil porte désormais les deux.
            'clock' => $this->horloge->etat($mission),
            // PEUT-ON ENCORE PROLONGER, ET JUSQU'OÙ.
            'extension' => $mission->booking !== null
                ? $this->extensions->etatDeLaProlongation($mission->booking)
                : null,
            'progress' => $avancement,
            'entries' => $entrees,
        ];
    }

    /**
     * Les items de checklist cochés, sur toutes les inspections de la mission.
     *
     * @return array{done: int, total: int, percent: int}
     */
    /** L'AVANCEMENT COMPTE LA CHECKLIST QUE LE CLIENT VOIT, PAS CELLE DE L'INSPECTION. */
    /** @return array{done: int, total: int, percent: int} */
    public function avancement(Mission $mission): array
    {
        $requete = MissionChecklistItem::query()
            ->whereIn('mission_checklist_id', function ($q) use ($mission) {
                $q->select('id')
                    ->from('mission_checklists')
                    ->where('mission_id', $mission->id);
            });

        $total = (clone $requete)->count();
        $faits = (clone $requete)->where('status', MissionChecklistService::FAITE)->count();

        return [
            'done' => $faits,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($faits / $total * 100) : 0,
        ];
    }

    public function finEstimee(Mission $mission): ?Carbon
    {
        if ($mission->actual_end_at !== null) {
            return $mission->actual_end_at;
        }

        if ($mission->actual_start_at === null) {
            return null;
        }

        $minutes = (int) ($mission->estimated_duration_minutes ?? 0);

        if ($minutes <= 0) {
            return $mission->planned_end_at;
        }

        return $mission->actual_start_at->copy()->addMinutes($minutes);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function depuisLesEvenements(Mission $mission): array
    {
        // LES JALONS SEULEMENT.
        $jalons = [
            'mission_en_route' => 'En route',
            'mission_arrived' => 'Arrivé sur place',
            'mission_started' => 'Intervention démarrée',
            'mission_started_qr' => 'Intervention démarrée',
            'mission_completed' => 'Intervention terminée',
        ];

        return MissionEvent::query()
            ->where('mission_id', $mission->id)
            ->whereIn('event_type', array_keys($jalons))
            ->orderBy('happened_at')
            ->get()
            ->map(fn (MissionEvent $e) => [
                'kind' => 'milestone',
                'key' => $e->event_type,
                'label' => $jalons[$e->event_type] ?? $e->title,
                'at' => $e->happened_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function depuisLesItemsDInspection(Mission $mission): array
    {
        return InspectionItem::query()
            ->whereIn('inspection_id', function ($q) use ($mission) {
                $q->select('id')
                    ->from('mission_quality_inspections')
                    ->where('mission_id', $mission->id);
            })
            ->whereNotNull('recorded_at')
            ->with('checklistItem')
            ->orderBy('recorded_at')
            ->get()
            ->map(function (InspectionItem $item) {
                // Un item d'inspection peut pointer une ligne de checklist supprimée depuis : le
                // fil doit rester lisible plutôt que d'afficher un vide au milieu du déroulé.
                $libelle = $item->checklistItem?->label;

                return [
                    'kind' => 'checklist',
                    'key' => 'inspection_item:'.$item->id,
                    'label' => $libelle !== null && $libelle !== '' ? $libelle : 'Étape validée',
                    'met' => (bool) $item->met,
                    'at' => $item->recorded_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function depuisLesIncidents(Mission $mission, bool $vueClient): array
    {
        return $this->incidentService
            ->pourLaMission($mission, $vueClient)
            ->map(fn ($incident) => [
                'kind' => 'incident',
                'key' => 'incident:'.$incident->id,
                'label' => (string) $incident->title,
                'severity' => $incident->severity,
                'at' => $incident->reported_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function depuisLesMedias(Mission $mission, bool $vueClient): array
    {
        return $this->mediaService
            ->pourLaMission($mission, $vueClient)
            ->map(fn ($media) => [
                'kind' => 'media',
                'key' => 'media:'.$media->id,
                'label' => $this->mediaService->libelleType((string) $media->media_type),
                'media_type' => $media->media_type,
                'at' => $media->taken_at?->toIso8601String(),
            ])
            ->all();
    }
}
