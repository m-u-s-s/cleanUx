<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionChecklist;
use App\Support\Domain\MissionEngine;

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

        /*
         * LA LISTE NAÎT VIDE — et c'est le renversement du module.
         *
         * Elle posait ici les six tâches du gabarit, toutes OBLIGATOIRES. Le prestataire cochait
         * donc six cases que personne ne lui avait demandées, pendant que ce que le CLIENT voulait
         * n'existait nulle part : il n'avait aucun moyen de dire « la hotte, surtout », et la seule
         * liste qui barre la clôture ignorait sa demande.
         *
         * Le savoir-faire du gabarit n'est pas jeté : il vit dans `suggestionsPour()`, que le
         * client ajoute d'un tap. Ce qui change, c'est QUI DÉCIDE.
         *
         * Conséquence assumée : une mission dont le client n'a rien listé se clôture sans cocher
         * quoi que ce soit. C'est le comportement voulu — on ne bloque un prestataire que sur une
         * demande réelle.
         */

        return $checklist->fresh('items');
    }

    /**
     * LE GABARIT, RENDU COMME PROPOSITIONS.
     *
     * Ce sont les mêmes libellés qu'avant, au même endroit, tirés du même arbre de décision par
     * métier. Seul leur statut change : ils ne s'imposent plus, ils s'offrent. Le client les
     * ajoute d'un tap depuis « Gérer ma mission », et ils deviennent alors des tâches `client`
     * comme les autres.
     *
     * Une course n'en reçoit aucune, pour la raison qui lui refuse déjà la checklist : il n'y a
     * rien à cocher sur un trajet.
     *
     * @return list<string>
     */
    public function suggestionsPour(Mission $mission): array
    {
        $mission->loadMissing(['serviceCatalog', 'booking']);

        if (! MissionEngine::accepteLaToDoList(MissionEngine::pourMission($mission))) {
            return [];
        }

        return array_values($this->resolveTemplate($mission)['items']);
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
