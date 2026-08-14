<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionChecklist;
use App\Models\MissionChecklistItem;

class MissionChecklistService
{
    /**
     * LE VOCABULAIRE DE `mission_checklist_items.status`, écrit une fois pour toutes.
     *
     * La colonne le déclare dans sa propre migration — « todo, done » — et c'est `done` que la
     * porte de clôture attend ({@see MissionLifecycleService::assertRequiredChecklistCompleted()}).
     * Ce service écrivait `pending`, l'écran terrain basculait vers `completed`, et la porte ne
     * reconnaissait ni l'un ni l'autre : cocher toutes les tâches laissait la mission bloquée,
     * avec un avancement à 100 % affiché juste à côté du refus.
     */
    public const A_FAIRE = 'todo';

    public const FAITE = 'done';

    /**
     * La checklist d'une mission — ou AUCUNE, quand la mission n'en appelle pas.
     *
     * UNE COURSE N'A PAS DE CHECKLIST DE MÉNAGE. Sans service au catalogue, le gabarit par défaut
     * s'appliquait, et un chauffeur arrivé à destination devait cocher « Nettoyer surfaces clés »
     * pour terminer. Comme ces tâches sont obligatoires, la course ne se terminait JAMAIS : ni
     * encaissement, ni avis client, et un prestataire qui reste « occupé » indéfiniment.
     *
     * Le refus vit ici, pas chez les appelants : les deux qui existent créeraient sinon chacun la
     * leur, et le troisième qu'on ajoutera un jour n'y penserait pas.
     */
    public function ensureChecklist(Mission $mission): ?MissionChecklist
    {
        $mission->loadMissing(['serviceCatalog', 'booking']);

        if ($mission->booking?->estUneCourse()) {
            return null;
        }

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
                    'status' => self::A_FAIRE,
                ]);
            }
        }

        return $checklist->fresh('items');
    }

    public function refreshProgress(MissionChecklist $checklist): MissionChecklist
    {
        $total = $checklist->items()->count();
        // `done`, comme la porte de clôture : un avancement qui compte autre chose annonce 100 %
        // pendant que la clôture refuse, et c'est le refus que le prestataire croit faux.
        $done = $checklist->items()->where('status', self::FAITE)->count();

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
