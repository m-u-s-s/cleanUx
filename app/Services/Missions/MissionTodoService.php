<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionChecklistItem;
use App\Models\User;
use App\Support\Domain\MissionEngine;
use App\Support\Domain\MissionStatus;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * LA LISTE QUE LE CLIENT ÉCRIT LUI-MÊME — et qui conditionne la clôture du prestataire.
 *
 * ── POURQUOI ELLE N'A PAS SA PROPRE TABLE ────────────────────────────────────────────────────
 *
 * Elle écrit dans `mission_checklist_items`, celle que
 * `MissionLifecycleService::assertRequiredChecklistCompleted()` interroge. C'est tout l'objet du
 * module : ce que le client demande DOIT être ce qui barre la porte. Une table dédiée aurait donné
 * deux listes — l'une affichée, l'autre bloquante — et ce dépôt a déjà payé ce défaut trois fois.
 *
 * ── LA FENÊTRE, ET LES DEUX ABUS QU'ELLE FERME ───────────────────────────────────────────────
 *
 * Sans borne, un client ajoute trois tâches lourdes à 18 h et retient chez lui un prestataire qui
 * croyait avoir fini. La fenêtre court donc à partir du DÉMARRAGE : avant, personne ne travaille et
 * le client peut écrire librement ; après, il lui reste un temps annoncé, minuteur à l'appui.
 *
 * Et parce qu'une garde qui ne protège qu'un côté devient une arme pour l'autre, chaque ajout
 * ROUVRE au prestataire une fenêtre de révision de devis — la symétrie vit dans
 * `config('missions.requote_reopen_minutes')`, lue par le module de révision.
 *
 * ── `locked_at` ATTESTE, IL NE PILOTE PAS ────────────────────────────────────────────────────
 *
 * La fenêtre se calcule depuis `actual_start_at` : c'est la seule source. La colonne enregistre
 * l'instant où un refus a été opposé, et rien d'autre. En faire le pilote créerait deux vérités,
 * qui divergeraient au premier changement de configuration.
 */
class MissionTodoService
{
    public function __construct(
        private readonly MissionChecklistService $checklists,
    ) {}

    /**
     * L'état de la fenêtre, tel que les deux écrans le lisent.
     *
     * @return array{open: bool, closes_at: ?string, minutes_left: ?int, reason: ?string}
     */
    public function fenetre(Mission $mission): array
    {
        $moteur = MissionEngine::pourMission($mission);

        if (! MissionEngine::accepteLaToDoList($moteur)) {
            return $this->fermee('Une course n’a pas de liste de tâches : le trajet en tient lieu.');
        }

        if (in_array($mission->status, [MissionStatus::COMPLETED, MissionStatus::CANCELLED], true)) {
            return $this->fermee('L’intervention est terminée : la liste ne peut plus changer.');
        }

        $debut = $mission->actual_start_at;

        /*
         * PAS ENCORE DÉMARRÉE = OUVERTE SANS ÉCHÉANCE, et c'est juste : le prestataire n'a rien pu
         * faire, aucune tâche ajoutée ne le retient. Poser une échéance ici fermerait la liste
         * d'une réservation prise trois semaines à l'avance.
         */
        if ($debut === null) {
            return ['open' => true, 'closes_at' => null, 'minutes_left' => null, 'reason' => null];
        }

        $echeance = $debut->copy()->addMinutes($this->fenetreEnMinutes());
        $maintenant = Carbon::now();

        if ($maintenant->greaterThanOrEqualTo($echeance)) {
            return $this->fermee('La liste est figée depuis '.$echeance->format('H:i').'.', $echeance);
        }

        return [
            'open' => true,
            'closes_at' => $echeance->toIso8601String(),
            // Arrondi au SUPÉRIEUR : annoncer « 0 min » alors qu'il reste quarante secondes ferait
            // renoncer quelqu'un qui avait encore le temps d'écrire.
            'minutes_left' => (int) ceil($maintenant->floatDiffInMinutes($echeance)),
            'reason' => null,
        ];
    }

    /**
     * Le client ajoute une tâche. Elle est OBLIGATOIRE : c'est tout l'objet du module — sans quoi
     * elle n'empêcherait pas la clôture et ne serait qu'un commentaire.
     *
     * @throws DomainException
     */
    public function ajouter(Mission $mission, User $client, string $label): MissionChecklistItem
    {
        $this->assertModifiable($mission);

        $propre = trim($label);

        if ($propre === '') {
            throw new DomainException('Dites en une phrase ce qui doit être fait.');
        }

        if (mb_strlen($propre) > 191) {
            throw new DomainException('Une tâche tient en une phrase : raccourcissez-la.');
        }

        $checklist = $this->checklists->ensureChecklist($mission);

        if ($checklist === null) {
            throw new DomainException('Cette mission n’accepte pas de liste de tâches.');
        }

        return MissionChecklistItem::query()->create([
            'mission_checklist_id' => $checklist->id,
            'label' => $propre,
            'item_type' => 'checkbox',
            'is_required' => true,
            'status' => MissionChecklistService::A_FAIRE,
            'source' => 'client',
            'created_by_user_id' => $client->id,
        ]);
    }

    /**
     * Le client retire une tâche — la sienne, et pas encore faite.
     *
     * ON NE RETIRE PAS CE QUI EST FAIT : le prestataire a travaillé dessus, et l'effacer lui
     * retirerait la preuve de ce qu'il a accompli.
     *
     * @throws DomainException
     */
    public function retirer(Mission $mission, User $client, MissionChecklistItem $item): void
    {
        $this->assertModifiable($mission);

        if (! $mission->checklists()->pluck('id')->contains($item->mission_checklist_id)) {
            throw new DomainException('Cette tâche n’appartient pas à cette intervention.');
        }

        if ($item->source !== 'client') {
            throw new DomainException('Cette tâche ne vient pas de vous.');
        }

        if ($item->status === MissionChecklistService::FAITE) {
            throw new DomainException('Le prestataire a déjà fait cette tâche.');
        }

        $item->delete();
    }

    /**
     * Ce que l'écran client affiche : le moteur, la fenêtre, les tâches, et les suggestions.
     *
     * @return array{engine: string, window: array<string, mixed>, items: list<array<string, mixed>>, suggestions: list<string>}
     */
    public function pourLeClient(Mission $mission): array
    {
        $mission->loadMissing('checklists.items');

        $items = $mission->checklists
            ->flatMap(fn ($liste) => $liste->items)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values()
            ->map(fn (MissionChecklistItem $item) => [
                'id' => $item->id,
                'label' => $item->label ?: $item->title,
                'source' => $item->source,
                'done' => $item->status === MissionChecklistService::FAITE,
                'is_required' => (bool) $item->is_required,
                // Ce que le client a le droit de retirer, tranché ICI plutôt que par l'écran :
                // deux surfaces en tireraient deux règles, et l'une des deux se tromperait.
                'removable' => $item->source === 'client'
                    && $item->status !== MissionChecklistService::FAITE,
            ])->all();

        return [
            'engine' => MissionEngine::pourMission($mission),
            'window' => $this->fenetre($mission),
            'items' => $items,
            'suggestions' => $this->checklists->suggestionsPour($mission),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** @throws DomainException */
    private function assertModifiable(Mission $mission): void
    {
        $fenetre = $this->fenetre($mission);

        if ($fenetre['open']) {
            return;
        }

        /*
         * LE VERROUILLAGE S'ATTESTE AU MOMENT DU REFUS, jamais sur une lecture. Une écriture
         * déclenchée par un simple affichage se produirait des dizaines de fois par mission, et
         * sur une réplique de lecture elle échouerait purement et simplement.
         */
        $this->verrouiller($mission);

        throw new DomainException((string) $fenetre['reason']);
    }

    /** Marque les tâches comme figées. Idempotent : seules celles qui ne le sont pas sont touchées. */
    private function verrouiller(Mission $mission): void
    {
        $listes = $mission->checklists()->pluck('id');

        if ($listes->isEmpty()) {
            return;
        }

        MissionChecklistItem::query()
            ->whereIn('mission_checklist_id', $listes)
            ->whereNull('locked_at')
            ->update(['locked_at' => Carbon::now()]);
    }

    /**
     * @return array{open: bool, closes_at: ?string, minutes_left: ?int, reason: ?string}
     */
    private function fermee(string $raison, ?Carbon $echeance = null): array
    {
        return [
            'open' => false,
            'closes_at' => $echeance?->toIso8601String(),
            'minutes_left' => 0,
            'reason' => $raison,
        ];
    }

    private function fenetreEnMinutes(): int
    {
        return max(0, (int) Config::get('missions.todo_window_minutes', 30));
    }
}
