<?php

namespace App\Services\Workforce;

use App\Models\Mission;
use App\Models\TimeEntry;
use App\Models\TripTrackingSession;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** LES FEUILLES D'HEURES (E20) — ce qui s'est réellement passé. */
class TimesheetService
{
    /** Enregistre le pointage d'une mission depuis sa session de suivi. */
    public function pointerDepuisLeSuivi(Mission $mission, TripTrackingSession $session): ?TimeEntry
    {
        $userId = (int) ($session->provider_user_id ?? 0);
        $organisationId = (int) ($mission->provider_organization_id ?? 0);

        if ($userId <= 0 || $organisationId <= 0 || ! $session->in_mission_at) {
            // Un indépendant n'a pas de feuille d'heures, et une session qui n'est jamais entrée en
            // mission n'a rien à pointer.
            return null;
        }

        $minutes = $session->workedMinutes();

        return TimeEntry::query()->updateOrCreate(
            ['mission_id' => $mission->id, 'user_id' => $userId],
            [
                'organization_account_id' => $organisationId,
                'started_at' => $session->in_mission_at,
                'ended_at' => $session->ended_at,
                'worked_minutes' => $minutes,
                'paused_minutes' => (int) round(((int) $session->paused_total_seconds) / 60),
                'source' => TimeEntry::SOURCE_AUTO,
                // Relevée par la géo-barrière : elle fait foi sans autre formalité.
                'status' => TimeEntry::STATUS_RECORDED,
            ],
        );
    }

    /**
     * Une correction saisie à la main — qui attend une approbation.
     *
     * @throws DomainException
     */
    public function saisirManuellement(
        User $worker,
        int $organisationId,
        Carbon $debut,
        Carbon $fin,
        ?Mission $mission = null,
        ?string $motif = null,
    ): TimeEntry {
        if ($fin->lessThanOrEqualTo($debut)) {
            throw new DomainException('La fin doit suivre le début.');
        }

        $minutes = (int) abs($fin->diffInMinutes($debut));

        if ($minutes > 16 * 60) {
            // Au-delà de seize heures, c'est une erreur de saisie ou un oubli de clôture : accepter
            // ferait passer une journée impossible dans une paie.
            throw new DomainException('Une journée de plus de seize heures se corrige, elle ne se saisit pas.');
        }

        return TimeEntry::query()->create([
            'organization_account_id' => $organisationId,
            'user_id' => $worker->id,
            'mission_id' => $mission?->id,
            'started_at' => $debut,
            'ended_at' => $fin,
            'worked_minutes' => $minutes,
            'source' => TimeEntry::SOURCE_MANUAL,
            'status' => TimeEntry::STATUS_PENDING_APPROVAL,
            'notes' => $motif,
        ]);
    }

    /**
     * Un responsable approuve — ou refuse — une saisie manuelle.
     *
     * @throws DomainException
     */
    public function statuer(TimeEntry $entry, User $responsable, bool $approuve): TimeEntry
    {
        if ($entry->status !== TimeEntry::STATUS_PENDING_APPROVAL) {
            throw new DomainException('Cette ligne n’attend pas d’approbation.');
        }

        if ((int) $entry->user_id === (int) $responsable->id) {
            // S'approuver soi-même viderait l'approbation de son sens : la correction redeviendrait
            // purement déclarative.
            throw new DomainException('Une correction ne s’approuve pas soi-même.');
        }

        $entry->forceFill([
            'status' => $approuve ? TimeEntry::STATUS_APPROVED : TimeEntry::STATUS_REJECTED,
            'approved_by_user_id' => $responsable->id,
            'approved_at' => now(),
        ])->save();

        return $entry->fresh();
    }

    /**
     * La feuille d'heures d'une période, par personne.
     *
     * @return Collection<int, array{user_id: int, name: mixed, entries_count: int<0, max>, worked_minutes: int, paused_minutes: int, worked_hours: float}>
     */
    public function feuilleDeLaPeriode(int $organisationId, Carbon $debut, Carbon $fin): Collection
    {
        return TimeEntry::query()
            ->where('organization_account_id', $organisationId)
            ->whereBetween('started_at', [$debut, $fin])
            // Une saisie refusée ne compte pas, et une saisie en attente non plus : payer avant
            // approbation reviendrait à ne jamais approuver.
            ->whereIn('status', [TimeEntry::STATUS_RECORDED, TimeEntry::STATUS_APPROVED])
            ->with('user:id,name')
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $lignes, $userId) => [
                'user_id' => (int) $userId,
                'name' => $lignes->first()?->user?->name,
                'entries_count' => $lignes->count(),
                'worked_minutes' => (int) $lignes->sum('worked_minutes'),
                'paused_minutes' => (int) $lignes->sum('paused_minutes'),
                // Les heures décimales sont ce que consomment les logiciels de paie : les leur
                // faire recalculer depuis des minutes invite aux arrondis divergents.
                'worked_hours' => round($lignes->sum('worked_minutes') / 60, 2),
            ])
            ->values();
    }

    /** L'export paie, en CSV. */
    public function exporterCsv(int $organisationId, Carbon $debut, Carbon $fin): string
    {
        $lignes = ['user_id;nom;lignes;minutes_travaillees;heures_decimales'];

        foreach ($this->feuilleDeLaPeriode($organisationId, $debut, $fin) as $ligne) {
            $lignes[] = implode(';', [
                $ligne['user_id'],
                // Le point-virgule est le séparateur : le laisser dans un nom couperait la ligne.
                str_replace(';', ' ', (string) $ligne['name']),
                $ligne['entries_count'],
                $ligne['worked_minutes'],
                number_format($ligne['worked_hours'], 2, ',', ''),
            ]);
        }

        return implode("\n", $lignes)."\n";
    }
}
