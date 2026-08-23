<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionChecklistItem;
use App\Models\MissionMedia;
use App\Support\Domain\MissionEngine;
use App\Support\Domain\MissionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/** QUAND LE PRESTATAIRE PEUT ENCORE DIRE « CE DEVIS EST FAUX ». */
class QuoteRevisionWindow
{
    /**
     * @return array{open: bool, closes_at: ?string, reason: ?string}
     */
    public function etat(Mission $mission): array
    {
        $moteur = MissionEngine::pourMission($mission);

        if ($moteur === MissionEngine::VEHICULE) {
            return $this->fermee('Le prix d’une course est fixé par le trajet : il ne se révise pas.');
        }

        if ($moteur === MissionEngine::HORAIRE) {
            return $this->fermee(
                'Cette mission est vendue au temps : utilisez la prolongation, pas un nouveau devis.'
            );
        }

        if (in_array($mission->status, [MissionStatus::COMPLETED, MissionStatus::CANCELLED], true)) {
            return $this->fermee('L’intervention est terminée.');
        }

        // LA RÉOUVERTURE PRIME SUR TOUT LE RESTE — y compris sur une tâche déjà cochée.
        $rouverteJusqua = $this->rouverteJusqua($mission);

        // ELLE EST DÉPENSÉE DÈS QUE LE PRESTATAIRE A AGI APRÈS L'AJOUT.
        if ($rouverteJusqua !== null
            && Carbon::now()->lessThan($rouverteJusqua)
            && ! $this->aAgiDepuis($mission, $rouverteJusqua->copy()->subMinutes($this->reouvertureEnMinutes()))) {
            return [
                'open' => true,
                'closes_at' => $rouverteJusqua->toIso8601String(),
                'reason' => null,
            ];
        }

        if ($this->aCommence($mission)) {
            return $this->fermee(
                'Vous avez commencé l’intervention : un imprévu se propose désormais en supplément.'
            );
        }

        $echeance = $this->echeance($mission);

        if ($echeance !== null && Carbon::now()->greaterThanOrEqualTo($echeance)) {
            return $this->fermee(
                'Le délai pour réviser le devis est passé : un imprévu se propose en supplément.',
                $echeance,
            );
        }

        return [
            'open' => true,
            'closes_at' => $echeance?->toIso8601String(),
            'reason' => null,
        ];
    }

    /** La fenêtre est-elle ouverte ? Raccourci pour les gardes. */
    public function ouverte(Mission $mission): bool
    {
        return $this->etat($mission)['open'] === true;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** Le prestataire a-t-il agi après cet instant ? Sert à dépenser la réouverture. */
    private function aAgiDepuis(Mission $mission, Carbon $instant): bool
    {
        $listes = $mission->checklists()->pluck('id');

        $tache = $listes->isNotEmpty() && MissionChecklistItem::query()
            ->whereIn('mission_checklist_id', $listes)
            ->where('status', MissionChecklistService::FAITE)
            ->where('completed_at', '>=', $instant)
            ->exists();

        if ($tache) {
            return true;
        }

        return MissionMedia::query()
            ->where('mission_id', $mission->id)
            ->where('media_type', MissionMedia::TYPE_AFTER_PHOTO)
            ->where('created_at', '>=', $instant)
            ->exists();
    }

    private function reouvertureEnMinutes(): int
    {
        return max(0, (int) Config::get('missions.requote_reopen_minutes', 6));
    }

    /** Une tâche cochée ou une photo « après » : dans les deux cas, le travail a commencé. */
    private function aCommence(Mission $mission): bool
    {
        $listes = $mission->checklists()->pluck('id');

        $tacheFaite = $listes->isNotEmpty() && MissionChecklistItem::query()
            ->whereIn('mission_checklist_id', $listes)
            ->where('status', MissionChecklistService::FAITE)
            ->exists();

        if ($tacheFaite) {
            return true;
        }

        // `media_type` ET NON `type`.
        return MissionMedia::query()
            ->where('mission_id', $mission->id)
            ->where('media_type', MissionMedia::TYPE_AFTER_PHOTO)
            ->exists();
    }

    /** L'échéance de base : le démarrage s'il existe, l'arrivée sinon. */
    private function echeance(Mission $mission): ?Carbon
    {
        $ancre = $mission->actual_start_at
            ?? $mission->assignments()->whereNotNull('arrived_at')->min('arrived_at');

        if ($ancre === null) {
            return null;
        }

        return Carbon::parse($ancre)->addMinutes(
            max(0, (int) Config::get('missions.todo_window_minutes', 30))
        );
    }

    /** Jusqu'à quand la dernière tâche ajoutée par le client rouvre la fenêtre. */
    private function rouverteJusqua(Mission $mission): ?Carbon
    {
        $listes = $mission->checklists()->pluck('id');

        if ($listes->isEmpty()) {
            return null;
        }

        $dernier = MissionChecklistItem::query()
            ->whereIn('mission_checklist_id', $listes)
            ->where('source', 'client')
            ->max('created_at');

        if ($dernier === null) {
            return null;
        }

        return Carbon::parse($dernier)->addMinutes($this->reouvertureEnMinutes());
    }

    /**
     * @return array{open: bool, closes_at: ?string, reason: ?string}
     */
    private function fermee(string $raison, ?Carbon $echeance = null): array
    {
        return ['open' => false, 'closes_at' => $echeance?->toIso8601String(), 'reason' => $raison];
    }
}
