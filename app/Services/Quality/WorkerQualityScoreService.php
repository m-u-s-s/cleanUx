<?php

namespace App\Services\Quality;

use App\Models\Feedback;
use App\Models\Mission;
use App\Models\MissionQualityInspection;
use App\Models\OrganizationMember;
use App\Models\TripTrackingSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * LE SCORE QUALITÉ INTERNE D'UN EXÉCUTANT (E26).
 *
 * AUCUNE NOUVELLE COLLECTE. Les trois sources existent et ne se parlaient pas : les inspections
 * (Quality), les avis clients (Ratings), et l'heure d'arrivée relevée par le suivi. Chacune répond à
 * un tiers de la question — « le travail est-il fait », « le client est-il content », « la personne
 * arrive-t-elle à l'heure » — et aucune ne suffit seule. Un exécutant très bien noté qui arrive
 * systématiquement en retard fait perdre un contrat sans qu'aucun des trois écrans ne l'annonce.
 *
 * CE SCORE NE SORT PAS DE LA SOCIÉTÉ, et c'est le point le plus important. Il sert à repérer qui a
 * besoin d'aide, pas à classer publiquement : l'exposer côté client en ferait un outil de sélection,
 * ce qu'aucun exécutant n'a accepté en signant.
 *
 * UN SCORE SANS MATIÈRE NE SE FABRIQUE PAS. Trois missions ne disent rien de personne : sous le
 * seuil, on rend le détail avec `has_enough_data: false` plutôt qu'un nombre qui serait lu comme un
 * jugement. Une moyenne sur un échantillon d'une mission est du bruit affiché avec deux décimales.
 *
 * ET LES TROIS TERMES SONT RENDUS SÉPARÉMENT. Le score global sert à trier une liste ; c'est le
 * détail qui dit quoi faire — « ponctualité 55 % » appelle une conversation sur les trajets, pas un
 * rappel sur la qualité du travail.
 */
class WorkerQualityScoreService
{
    /** En dessous, on ne calcule pas : une moyenne sur deux missions est du bruit. */
    public const MISSIONS_MINIMUM = 3;

    /** Au-delà de ce retard, l'arrivée n'est plus « à l'heure ». */
    public const TOLERANCE_RETARD_MINUTES = 15;

    /**
     * Les poids des trois termes.
     *
     * L'AVIS CLIENT PÈSE LE PLUS, parce que c'est lui qui décide du renouvellement du contrat.
     * L'inspection vient ensuite — elle est objective mais ne mesure que ce que la checklist a
     * pensé à demander. La ponctualité pèse le moins tout en restant présente : un retard se
     * rattrape, il ne se cache pas.
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
     * Rendu comme une LISTE et non une Collection : c'est une charge utile, lue une fois par
     * l'écran ou sérialisée par l'API. Rien ici n'appelle de chaînage.
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
            /*
             * LES SANS-MATIÈRE FINISSENT EN BAS, PAS EN HAUT. `score` vaut `null` pour eux : trié
             * naïvement, un `null` remonterait en tête et le nouvel arrivant paraîtrait le pire de
             * l'équipe le jour de son embauche.
             */
            ->sortByDesc(fn (array $ligne) => $ligne['score'] ?? -1)
            ->values()
            ->all();
    }

    /**
     * La moyenne des inspections validées.
     *
     * `score_max` peut valoir zéro — une checklist sans item pondéré : diviser dessus produirait une
     * division par zéro sur une inspection parfaitement légitime.
     */
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

    /**
     * La part des arrivées dans les quinze minutes de l'heure prévue.
     *
     * MESURÉE SUR L'ARRIVÉE RELEVÉE, pas sur un statut. Une mission qu'on a oublié de démarrer n'est
     * pas un retard, et un démarrage tardif dans l'application n'en est pas un non plus : seule
     * `arrived_at`, posée par la géo-barrière, dit où était la personne et quand.
     */
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

    /**
     * Combine les trois termes, en REDISTRIBUANT le poids de ceux qui manquent.
     *
     * Traiter une source absente comme un zéro punirait quelqu'un pour une inspection que personne
     * n'a faite. Renormaliser sur les termes disponibles rend le score comparable entre deux
     * personnes qui n'ont pas les mêmes sources.
     */
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
