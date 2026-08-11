<?php

namespace App\Services\Missions\OnSite;

use App\Models\InspectionItem;
use App\Models\Mission;
use App\Models\MissionEvent;
use Illuminate\Support\Carbon;

/**
 * LA MISSION VUE DEPUIS LE SALON DU CLIENT — arrivé, démarré, ce qui est fait, quand ça finit.
 *
 * Le canal `mission.{id}` diffusait déjà des positions et des statuts, et les inspections
 * enregistraient déjà chaque item coché. Ce qui manquait n'était pas la donnée : c'était la LIGNE
 * qui les met bout à bout. Quatre tables racontent chacune un tiers de l'histoire, et le client
 * n'avait accès à aucune.
 *
 * Le fil est assemblé À LA LECTURE et non stocké. Une table de plus tenue à jour par cinq
 * écrivains finirait par diverger de ce qu'elle résume — et c'est le résumé qui serait affiché.
 *
 * L'HEURE DE FIN EST UNE ESTIMATION, et se présente comme telle. Elle vaut `actual_start_at` plus
 * la durée prévue au devis ; sans démarrage réel, il n'y en a pas — annoncer une fin pour une
 * mission qui n'a pas commencé est la meilleure façon de faire attendre quelqu'un pour rien.
 */
class MissionTimelineService
{
    public function __construct(
        protected MissionMediaService $mediaService,
        protected MissionIncidentService $incidentService,
    ) {}

    /**
     * Le fil complet d'une mission, du plus ancien au plus récent.
     *
     * @return array{
     *     mission_id: int,
     *     status: string|null,
     *     started_at: string|null,
     *     estimated_end_at: string|null,
     *     progress: array{done: int, total: int, percent: int},
     *     entries: list<array<string, mixed>>
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
            'progress' => $avancement,
            'entries' => $entrees,
        ];
    }

    /**
     * Les items de checklist cochés, sur toutes les inspections de la mission.
     *
     * @return array{done: int, total: int, percent: int}
     */
    public function avancement(Mission $mission): array
    {
        $requete = InspectionItem::query()
            ->whereIn('inspection_id', function ($q) use ($mission) {
                $q->select('id')
                    ->from('mission_quality_inspections')
                    ->where('mission_id', $mission->id);
            });

        $total = (clone $requete)->count();
        $faits = (clone $requete)->whereNotNull('recorded_at')->count();

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
        /*
         * LES JALONS SEULEMENT.
         *
         * `mission_events` sert aussi de journal technique — synchronisations hors ligne,
         * réaffectations, recalculs. Tout déverser donnerait au client une trace d'exploitation
         * là où il attend quatre lignes lisibles.
         */
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
