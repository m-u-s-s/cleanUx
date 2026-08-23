<?php

namespace App\Services\Missions;

use App\Models\MissionDisputeSignal;
use App\Models\MissionFeatureSuspension;
use App\Models\MissionQuoteRevision;
use App\Models\User;
use App\Notifications\SanctionAutomatiqueNotification;
use App\Support\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/** QUI TRICHE — et la seule façon honnête de le savoir. */
class QuoteRevisionArbiter
{
    /** Le nombre d'occurrences en deçà duquel on n'arbitre rien. */
    public const SEUIL_OCCURRENCES = 3;

    /** Et le nombre de contreparties distinctes sans lequel ces occurrences ne prouvent rien. */
    public const SEUIL_CONTREPARTIES = 2;

    /** ENREGISTRER CE QUI S'EST PASSÉ — sans juger. */
    public function enregistrer(MissionQuoteRevision $revision): ?MissionDisputeSignal
    {
        $reservation = $revision->booking;

        if ($reservation === null || $reservation->client_id === null) {
            return null;
        }

        [$issue, $cote] = match (true) {
            $revision->status === MissionQuoteRevision::STATUT_ACCEPTEE => [
                MissionDisputeSignal::ISSUE_ACCEPTEE,
                MissionDisputeSignal::COTE_CLIENT,
            ],
            $revision->status === MissionQuoteRevision::STATUT_REFUSEE
                && $revision->client_decision === MissionQuoteRevision::DECISION_ARRETER => [
                    MissionDisputeSignal::ISSUE_REFUSEE_ARRET,
                    MissionDisputeSignal::COTE_PRESTATAIRE,
                ],
            $revision->status === MissionQuoteRevision::STATUT_REFUSEE => [
                MissionDisputeSignal::ISSUE_REFUSEE_POURSUITE,
                MissionDisputeSignal::COTE_PRESTATAIRE,
            ],
            $revision->status === MissionQuoteRevision::STATUT_EXPIREE => [
                MissionDisputeSignal::ISSUE_EXPIREE,
                MissionDisputeSignal::COTE_INDETERMINE,
            ],
            default => [null, null],
        };

        if ($issue === null) {
            return null;
        }

        // IDEMPOTENT : une révision ne produit qu'un signal, même si la clôture est rejouée.
        $existant = MissionDisputeSignal::query()
            ->where('quote_revision_id', $revision->id)
            ->first();

        if ($existant !== null) {
            return $existant;
        }

        return MissionDisputeSignal::query()->create([
            'mission_id' => $revision->mission_id,
            'booking_id' => $revision->booking_id,
            'quote_revision_id' => $revision->id,
            'provider_user_id' => $revision->proposed_by_user_id,
            'client_user_id' => $reservation->client_id,
            'signal_code' => 'quote_revision',
            'charged_side' => $cote,
            'outcome' => $issue,
            'evidence' => [
                'original_total_cents' => $revision->original_total_cents,
                'revised_total_cents' => $revision->revised_total_cents,
                'reason_code' => $revision->reason_code,
                'media_count' => count($revision->evidence_media_ids ?? []),
            ],
            'verdict' => MissionDisputeSignal::VERDICT_AUCUN,
        ]);
    }

    /** ARBITRER — le motif est-il établi, et contre qui ? */
    public function verdictPour(MissionDisputeSignal $signal): string
    {
        if ($this->ententeSuspectee($signal)) {
            return MissionDisputeSignal::VERDICT_INDECIS;
        }

        if ($signal->charged_side === MissionDisputeSignal::COTE_CLIENT
            && $this->motifEtabli($signal->client_user_id, MissionDisputeSignal::COTE_CLIENT)) {
            return MissionDisputeSignal::VERDICT_CLIENT;
        }

        if ($signal->charged_side === MissionDisputeSignal::COTE_PRESTATAIRE
            && $this->motifEtabli($signal->provider_user_id, MissionDisputeSignal::COTE_PRESTATAIRE)) {
            return MissionDisputeSignal::VERDICT_PRESTATAIRE;
        }

        return MissionDisputeSignal::VERDICT_AUCUN;
    }

    /** Poser le verdict, et en tirer la sanction s'il y a lieu. */
    public function arbitrer(MissionDisputeSignal $signal): MissionDisputeSignal
    {
        $verdict = $this->verdictPour($signal);

        $signal->forceFill(['verdict' => $verdict, 'verdict_at' => Carbon::now()])->save();

        if ($verdict === MissionDisputeSignal::VERDICT_PRESTATAIRE) {
            $this->sanctionner($signal->provider, MissionFeatureSuspension::OPTION_REVISION, $signal);
        }

        if ($verdict === MissionDisputeSignal::VERDICT_CLIENT) {
            $this->sanctionner($signal->client, MissionFeatureSuspension::OPTION_COMMANDE, $signal);
        }

        return $signal->fresh();
    }

    /** LE MOTIF EST-IL ÉTABLI CONTRE CETTE PERSONNE ? */
    public function motifEtabli(int $userId, string $cote): bool
    {
        $colonne = $cote === MissionDisputeSignal::COTE_CLIENT ? 'client_user_id' : 'provider_user_id';
        $contrepartie = $cote === MissionDisputeSignal::COTE_CLIENT ? 'provider_user_id' : 'client_user_id';

        $signaux = MissionDisputeSignal::query()
            ->where($colonne, $userId)
            ->where('charged_side', $cote)
            ->where('created_at', '>=', Carbon::now()->subDays($this->fenetreEnJours()))
            ->get([$contrepartie, 'id']);

        if ($signaux->count() < self::SEUIL_OCCURRENCES) {
            return false;
        }

        return $signaux->pluck($contrepartie)->unique()->count() >= self::SEUIL_CONTREPARTIES;
    }

    /** L'ENTENTE SE CONCENTRE SUR UN COUPLE. */
    public function ententeSuspectee(MissionDisputeSignal $signal): bool
    {
        if ($signal->outcome !== MissionDisputeSignal::ISSUE_REFUSEE_ARRET) {
            return false;
        }

        return MissionDisputeSignal::query()
            ->where('provider_user_id', $signal->provider_user_id)
            ->where('client_user_id', $signal->client_user_id)
            ->where('outcome', MissionDisputeSignal::ISSUE_REFUSEE_ARRET)
            ->where('created_at', '>=', Carbon::now()->subDays($this->fenetreEnJours()))
            ->count() >= 2;
    }

    /** LA SANCTION, GRADUÉE — 14 jours, puis 60, puis définitive. */
    public function sanctionner(?User $personne, string $option, MissionDisputeSignal $signal): ?MissionFeatureSuspension
    {
        if ($personne === null) {
            return null;
        }

        // Déjà suspendu : on ne superpose pas deux peines pour le même motif.
        $enCours = MissionFeatureSuspension::query()
            ->where('user_id', $personne->id)
            ->where('feature', $option)
            ->actives()
            ->exists();

        if ($enCours) {
            return null;
        }

        $palier = MissionFeatureSuspension::query()
            ->where('user_id', $personne->id)
            ->where('feature', $option)
            ->count() + 1;

        $duree = match ($palier) {
            1 => 14,
            2 => 60,
            default => null,   // définitif
        };

        $suspension = DB::transaction(fn () => MissionFeatureSuspension::query()->create([
            'user_id' => $personne->id,
            'feature' => $option,
            'level' => min(3, $palier),
            'starts_at' => Carbon::now(),
            'ends_at' => $duree === null ? null : Carbon::now()->addDays($duree),
            'reason' => 'Motif établi sur '.self::SEUIL_OCCURRENCES.' occurrences et '
                .self::SEUIL_CONTREPARTIES.' contreparties distinctes (signal #'.$signal->id.').',
        ]));

        // L'ALERTE ADMINISTRATEUR EST OBLIGATOIRE À CHAQUE BLOCAGE AUTOMATIQUE — exigence du porteur.
        ActivityLogger::system('mission.sanction_automatique', $suspension, [
            'user_id' => $personne->id,
            'feature' => $option,
            'level' => $suspension->level,
            'ends_at' => $suspension->ends_at?->toIso8601String(),
            'signal_id' => $signal->id,
        ]);

        foreach ($this->administrateurs() as $admin) {
            $admin->notify(new SanctionAutomatiqueNotification($suspension, $signal));
        }

        return $suspension;
    }

    /**
     * Les administrateurs à prévenir.
     *
     * @return Collection<int, User>
     */
    private function administrateurs()
    {
        return User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->get();
    }

    private function fenetreEnJours(): int
    {
        return max(1, (int) Config::get('missions.arbitration_window_days', 90));
    }
}
