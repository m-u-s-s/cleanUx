<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionChecklist;
use App\Models\MissionChecklistItem;

class MissionChecklistService
{
    public function ensureChecklist(Mission $mission): MissionChecklist
    {
        $mission->loadMissing('serviceCatalog');

        $template = $this->resolveTemplate($mission);

        $checklist = MissionChecklist::query()->firstOrCreate(
            ['mission_id' => $mission->id],
            [
                'service_catalog_id' => $mission->service_catalog_id,
                'template_name' => $template['name'],
                'status' => 'draft',
                'completion_rate' => 0,
            ]
        );

        if (! $checklist->items()->exists()) {
            foreach ($template['items'] as $label) {
                MissionChecklistItem::query()->create([
                    'mission_checklist_id' => $checklist->id,
                    'label' => $label,
                    'item_type' => 'checkbox',
                    'is_required' => true,
                    'status' => 'pending',
                ]);
            }
        }

        return $checklist->fresh('items');
    }

    public function refreshProgress(MissionChecklist $checklist): MissionChecklist
    {
        $total = $checklist->items()->count();
        $done = $checklist->items()->where('status', 'completed')->count();

        $rate = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        $checklist->update([
            'completion_rate' => $rate,
            'status' => $rate >= 100 ? 'completed' : ($done > 0 ? 'in_progress' : 'draft'),
        ]);

        return $checklist->fresh('items');
    }

    protected function resolveTemplate(Mission $mission): array
    {
        $code = strtolower((string) ($mission->serviceCatalog?->code ?? ''));
        $slug = strtolower((string) ($mission->serviceCatalog?->slug ?? ''));
        $serviceType = strtolower((string) ($mission->serviceCatalog?->service_type ?? ''));

        $key = $code ?: ($slug ?: $serviceType);

        return match (true) {
            str_contains($key, 'vitre') || str_contains($key, 'window') => [
                'name' => 'Nettoyage vitres',
                'items' => [
                    'Vérifier accès et sécurité',
                    'Préparer matériel vitres',
                    'Nettoyer faces intérieures',
                    'Nettoyer faces extérieures',
                    'Essuyer contours et finitions',
                    'Contrôle qualité final',
                ],
            ],

            str_contains($key, 'bureau') || str_contains($key, 'office') => [
                'name' => 'Nettoyage bureaux',
                'items' => [
                    'Sécuriser les zones de travail',
                    'Vider corbeilles',
                    'Dépoussiérer postes et surfaces',
                    'Nettoyer sanitaires',
                    'Nettoyer sols',
                    'Contrôle qualité final',
                ],
            ],

            str_contains($key, 'chantier') => [
                'name' => 'Fin de chantier',
                'items' => [
                    'Sécuriser le périmètre',
                    'Évacuer poussières et déchets légers',
                    'Nettoyer surfaces principales',
                    'Nettoyer sols et angles',
                    'Contrôler zones sensibles',
                    'Photos de fin de chantier',
                ],
            ],

            default => [
                'name' => 'Checklist standard',
                'items' => [
                    'Vérifier accès client',
                    'Préparer matériel',
                    'Nettoyer pièces prévues',
                    'Nettoyer surfaces clés',
                    'Contrôle qualité',
                    'Rangement du matériel',
                ],
            ],
        };
    }
}