<?php

namespace App\Services\Missions\OnSite;

use App\Models\Mission;
use App\Models\MissionChecklist;
use App\Models\MissionChecklistItem;
use App\Models\User;

/**
 * LES TÂCHES QUI BLOQUENT LA CLÔTURE — lisibles et cochables, y compris depuis le mobile.
 *
 * `MissionLifecycleService::assertRequiredChecklistCompleted()` refuse de clôturer tant qu'une
 * tâche `is_required` n'est pas `done`. Ce refus était SANS REMÈDE dans l'application prestataire :
 * l'écran « Mission terrain » affichait la checklist du module Inspection — une autre table — et
 * le seul chemin vers celle-ci passait par des routes web à session, inaccessibles à une
 * application authentifiée par jeton.
 *
 * POURQUOI PAS LE PARCOURS GUIDÉ. `MissionGuidedChecklistService` existe et sert une marche
 * ordonnée, une étape à la fois, avec photo obligatoire. Il exige que CHAQUE tâche porte un
 * `sort_order` : les checklists issues des gabarits n'en ont pas, et il rend alors `estGuidee()`
 * faux — donc rien. Les deux coexistent volontairement : le parcours guidé est une discipline
 * qu'on choisit, cette liste-ci est le minimum sans lequel une mission ne peut pas se terminer.
 */
class MissionChecklistService
{
    /**
     * Ce que le prestataire doit voir : les tâches, et surtout ce qui l'empêche de clôturer.
     *
     * @return array<string, mixed>
     */
    public function pour(Mission $mission): array
    {
        $mission->loadMissing('checklists.items');

        $checklists = $mission->checklists->map(fn (MissionChecklist $checklist) => [
            'id' => $checklist->id,
            'name' => $checklist->template_name,
            'status' => $checklist->status,
            'completion_rate' => (float) ($checklist->completion_rate ?? 0),
            'items' => $checklist->items
                // L'ordre déclaré s'il existe, sinon l'ordre de création : une liste dont l'ordre
                // change d'un appel à l'autre se recoche de travers.
                ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                ->values()
                ->map(fn (MissionChecklistItem $item) => [
                    'id' => $item->id,
                    'label' => $item->label ?: $item->title,
                    'guidance' => $item->guidance,
                    'is_required' => (bool) $item->is_required,
                    'requires_photo' => (bool) $item->requires_photo,
                    'status' => $item->status,
                    'done' => $item->status === 'done',
                ])->all(),
        ])->values()->all();

        $obligatoiresOuvertes = $mission->checklists
            ->flatMap(fn (MissionChecklist $c) => $c->items)
            ->filter(fn (MissionChecklistItem $i) => $i->is_required && $i->status !== 'done')
            ->count();

        return [
            'checklists' => $checklists,
            // LE CHIFFRE QUI COMPTE : c'est exactement la condition de
            // `assertRequiredChecklistCompleted()`. L'application peut donc dire au prestataire ce
            // qui le bloque, au lieu de lui opposer un refus sans explication.
            'required_pending' => $obligatoiresOuvertes,
            'blocks_completion' => $obligatoiresOuvertes > 0,
        ];
    }

    /**
     * Cocher ou décocher une tâche, et tenir à jour l'avancement de sa checklist.
     *
     * Décocher reste possible À DESSEIN : une tâche cochée par erreur juste avant la clôture doit
     * pouvoir être reprise, sans quoi le prestataire n'a plus que le mensonge ou l'abandon.
     */
    public function basculer(
        MissionChecklistItem $item,
        string $statut,
        ?string $notes,
        User $user,
    ): MissionChecklist {
        $item->update([
            'status' => $statut,
            'notes' => $notes ?? $item->notes,
            'completed_by_user_id' => $statut === 'done' ? $user->id : null,
            'completed_at' => $statut === 'done' ? now() : null,
        ]);

        $checklist = $item->checklist()->with('items')->first();

        $total = max(1, $checklist->items->count());
        $faites = $checklist->items->where('status', 'done')->count();

        $checklist->update([
            'completion_rate' => round(($faites / $total) * 100, 2),
            'status' => $faites === $checklist->items->count() ? 'completed' : 'in_progress',
        ]);

        return $checklist->fresh();
    }
}
