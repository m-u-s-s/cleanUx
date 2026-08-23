<?php

namespace App\Services\Quality;

use App\Models\Feedback;
use App\Models\Mission;
use App\Models\MissionQualityInspection;
use App\Models\OrganizationMember;
use App\Models\TripTrackingSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** LE SCORE QUALITÉ INTERNE D'UN EXÉCUTANT (E26). AUCUNE NOUVELLE COLLECTE. */
class WorkerQualityScoreService
{
    /** En dessous, on ne calcule pas : une moyenne sur deux missions est du bruit. */
    public const MISSIONS_MINIMUM = 3;

    /** Au-delà de ce retard, l'arrivée n'est plus « à l'heure ». */
    public const TOLERANCE_RETARD_MINUTES = 15;

    /**
     * Les poids des trois termes.
     *
     * @var array<string, float>
     */
    public const POIDS = [
        'satisfaction' => 0.45,
        'inspection' => 0.35,
        'ponctualite' => 0.20,
    ];

    /**
     * Le score d'une personne, sur une fenêtre.
     *
     * @return array<string, mixed>
     */
    public function pourLExecutant(int $organisationId, int $userId, ?Carbon $depuis = null): array
    {
        $depuis ??= Carbon::now()->subMonths(6);

        $missionIds = Mission::query()
            ->where('provider_organization_id', $organisationId)
            ->where('lead_provider_user_id', $userId)
            ->where('created_at', '>=', $depuis)
            ->pluck('id');

        $inspection = $this->scoreDInspection($missionIds);
        $satisfaction = $this->scoreDeSatisfaction($userId, $depuis);
        $ponctualite = $this->scoreDePonctualite($missionIds);

        $assezDeMatiere = $missionIds->count() >= self::MISSIONS_MINIMUM;

        return [
            'user_id' => $userId,
            'missions_count' => $missionIds->count(),
            // Sous le seuil, le détail se rend quand même : il sert à savoir ce qui manque.
            'has_enough_data' => $assezDeMatiere,
            'inspection_score' => $inspection,
            'satisfaction_score' => $satisfaction,
            'punctuality_score' => $ponctualite,
            'score' => $assezDeMatiere ? $this->composer($inspection, $satisfaction, $ponctualite) : null,
            'since' => $depuis->toDateString(),
        ];
    }

    /**
     * Le classement d'une société — pour l'écran du responsable qualité.
     *
     * @return list<array<string, mixed>>
     */
    public function pourLaSociete(int $organisationId, ?Carbon $depuis = null): array
    {
        $membres = OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->where('status', 'active')
            ->with('user:id,name')
            ->get();

        return $membres
            ->map(function (OrganizationMember $membre) use ($organisationId, $depuis) {
                $ligne = $this->pourLExecutant($organisationId, (int) $membre->user_id, $depuis);
                $ligne['name'] = $membre->user?->name;

                return $ligne;
            })
            // LES SANS-MATIÈRE FINISSENT EN BAS, PAS EN HAUT.
            ->sortByDesc(fn (array $ligne) => $ligne['score'] ?? -1)
            ->values()
            ->all();
    }

    /** La moyenne des inspections validées. */
    /** @param  Collection<int, int>  $missionIds */
    protected function scoreDInspection(Collection $missionIds): ?float
    {
        if ($missionIds->isEmpty()) {
            return null;
        }

        $inspections = MissionQualityInspection::query()
            ->whereIn('mission_id', $missionIds)
            ->where('score_max', '>', 0)
            ->get(['score_calculated', 'score_max']);

        if ($inspections->isEmpty()) {
            return null;
        }

        $total = $inspections->sum(fn (MissionQualityInspection $i) => (float) $i->score_calculated);
        $max = $inspections->sum(fn (MissionQualityInspection $i) => (int) $i->score_max);

        return $max > 0 ? round($total / $max * 100, 1) : null;
    }

    /** La moyenne des avis PUBLIÉS du client vers cette personne, ramenée sur cent. */
    protected function scoreDeSatisfaction(int $userId, Carbon $depuis): ?float
    {
        $notes = Feedback::query()
            ->where('employe_id', $userId)
            ->where('direction', Feedback::DIRECTION_CLIENT_TO_PROVIDER)
            ->where('status', Feedback::STATUS_PUBLISHED)
            ->where('is_hidden', false)
            ->where('created_at', '>=', $depuis)
            ->pluck('rating')
            ->filter(fn ($note) => is_numeric($note) && (float) $note > 0);

        if ($notes->isEmpty()) {
            return null;
        }

        return round($notes->avg() / 5 * 100, 1);
    }

    /** La part des arrivées dans les quinze minutes de l'heure prévue. */
    /** @param  Collection<int, int>  $missionIds */
    protected function scoreDePonctualite(Collection $missionIds): ?float
    {
        if ($missionIds->isEmpty()) {
            return null;
        }

        $missions = Mission::query()
            ->whereIn('id', $missionIds)
            ->whereNotNull('planned_start_at')
            ->get(['id', 'booking_id', 'planned_start_at']);

        if ($missions->isEmpty()) {
            return null;
        }

        $arrivees = TripTrackingSession::query()
            ->whereIn('booking_id', $missions->pluck('booking_id')->filter())
            ->whereNotNull('arrived_at')
            ->get(['booking_id', 'arrived_at'])
            ->keyBy('booking_id');

        $mesurees = 0;
        $aLHeure = 0;

        foreach ($missions as $mission) {
            $session = $arrivees->get($mission->booking_id);

            if ($session === null) {
                // Aucune arrivée relevée : on ne compte NI dans un sens ni dans l'autre. La compter
                // comme un retard punirait un GPS coupé ; comme une ponctualité, l'inverse.
                continue;
            }

            $mesurees++;

            $retard = $mission->planned_start_at->diffInMinutes($session->arrived_at, false);

            if ($retard <= self::TOLERANCE_RETARD_MINUTES) {
                $aLHeure++;
            }
        }

        return $mesurees > 0 ? round($aLHeure / $mesurees * 100, 1) : null;
    }

    /** Combine les trois termes, en REDISTRIBUANT le poids de ceux qui manquent. */
    protected function composer(?float $inspection, ?float $satisfaction, ?float $ponctualite): ?float
    {
        $termes = array_filter([
            'inspection' => $inspection,
            'satisfaction' => $satisfaction,
            'ponctualite' => $ponctualite,
        ], fn (?float $valeur) => $valeur !== null);

        if ($termes === []) {
            return null;
        }

        $poidsTotal = array_sum(array_map(fn (string $cle) => self::POIDS[$cle], array_keys($termes)));

        $somme = 0.0;

        foreach ($termes as $cle => $valeur) {
            $somme += $valeur * self::POIDS[$cle];
        }

        return round($somme / $poidsTotal, 1);
    }
}
