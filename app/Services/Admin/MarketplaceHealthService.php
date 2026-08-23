<?php

namespace App\Services\Admin;

use App\Models\AsapDispatchRequest;
use App\Models\MissionAssignment;
use App\Models\ServiceZone;
use App\Models\User;
use App\Support\Domain\AsapStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** LA SANTÉ DU MARCHÉ (E30) — offre contre demande, zone par zone. */
class MarketplaceHealthService
{
    /**
     * L'état du marché par zone.
     *
     * @return list<array<string, mixed>>
     */
    public function parZone(?Carbon $depuis = null, ?Carbon $jusqua = null): array
    {
        $depuis ??= Carbon::now()->subDays(30);
        $jusqua ??= Carbon::now();

        $recherches = AsapDispatchRequest::query()
            ->whereBetween('created_at', [$depuis, $jusqua])
            ->with('booking:id,service_zone_id')
            ->get(['id', 'booking_id', 'status', 'created_at', 'accepted_at', 'notified_count']);

        $offreParZone = $this->offreParZone();

        return ServiceZone::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (ServiceZone $zone) use ($recherches, $offreParZone) {
                $deLaZone = $recherches->filter(
                    fn (AsapDispatchRequest $r) => (int) ($r->booking->service_zone_id ?? 0) === (int) $zone->id,
                );

                $total = $deLaZone->count();
                $epuisees = $deLaZone->where('status', AsapStatus::EXPIRED)->count();
                $acceptees = $deLaZone->whereNotNull('accepted_at')->count();

                return [
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'searches_count' => $total,
                    'accepted_count' => $acceptees,
                    'exhausted_count' => $epuisees,
                    // LE SEUL CHIFFRE QUI COMMANDE UNE ACTION.
                    'no_candidate_rate' => $total > 0 ? round($epuisees / $total * 100, 1) : null,
                    'median_assignment_seconds' => $this->medianeDAssignation($deLaZone),
                    'providers_online' => $offreParZone[$zone->id] ?? 0,
                    // Le ratio offre/demande : une zone à 40 recherches pour 2 prestataires n'a pas
                    // le même problème qu'une zone à 2 recherches pour 40.
                    'demand_per_provider' => ($offreParZone[$zone->id] ?? 0) > 0
                        ? round($total / $offreParZone[$zone->id], 2)
                        : null,
                    // Une zone sans demande n'est pas une zone en bonne santé : la masquer ferait
                    // disparaître du tableau celles où l'on n'a jamais rien vendu.
                    'has_data' => $total > 0,
                ];
            })
            ->sortByDesc(fn (array $ligne) => $ligne['no_candidate_rate'] ?? -1)
            ->values()
            ->all();
    }

    /**
     * Le résumé de la plateforme — la ligne qu'on regarde en premier.
     *
     * @return array<string, mixed>
     */
    public function resume(?Carbon $depuis = null, ?Carbon $jusqua = null): array
    {
        $lignes = collect($this->parZone($depuis, $jusqua));

        $total = (int) $lignes->sum('searches_count');
        $epuisees = (int) $lignes->sum('exhausted_count');

        return [
            'searches_count' => $total,
            'exhausted_count' => $epuisees,
            'no_candidate_rate' => $total > 0 ? round($epuisees / $total * 100, 1) : null,
            // Les zones à surveiller : au-delà d'un cinquième de recherches sans candidat, la zone
            // ne tient plus, et c'est un problème de recrutement, pas de produit.
            'zones_at_risk' => $lignes
                ->filter(fn (array $l) => ($l['no_candidate_rate'] ?? 0) >= 20)
                ->pluck('zone_name')
                ->values()
                ->all(),
            'zones_without_data' => $lignes->where('has_data', false)->count(),
        ];
    }

    /**
     * Combien de temps met une recherche à trouver quelqu'un.
     *
     * @param  Collection<int, AsapDispatchRequest>  $recherches
     */
    protected function medianeDAssignation(Collection $recherches): ?int
    {
        $delais = $recherches
            ->filter(fn (AsapDispatchRequest $r) => $r->accepted_at !== null && $r->created_at !== null)
            ->map(fn (AsapDispatchRequest $r) => (int) $r->created_at->diffInSeconds($r->accepted_at))
            ->sort()
            ->values();

        if ($delais->isEmpty()) {
            return null;
        }

        $milieu = intdiv($delais->count(), 2);

        // La MÉDIANE : une recherche de quarante minutes un dimanche soir décalerait une moyenne au
        // point de rendre le chiffre inutilisable.
        return $delais->count() % 2 === 1
            ? $delais[$milieu]
            : (int) round(($delais[$milieu - 1] + $delais[$milieu]) / 2);
    }

    /**
     * Combien de prestataires servent chaque zone.
     *
     * @return array<int, int>
     */
    protected function offreParZone(): array
    {
        // DEUX CHEMINS, LES MÊMES QUE LE DISPATCH.
        $principales = User::query()
            ->whereNotNull('primary_service_zone_id')
            ->where('is_active', true)
            ->selectRaw('primary_service_zone_id as zone, COUNT(*) as total')
            ->groupBy('primary_service_zone_id')
            ->pluck('total', 'zone');

        $affectees = DB::table('employee_zone_assignments')
            ->where('is_active', true)
            ->selectRaw('service_zone_id as zone, COUNT(DISTINCT user_id) as total')
            ->groupBy('service_zone_id')
            ->pluck('total', 'zone');

        $total = [];

        foreach ([$principales, $affectees] as $source) {
            foreach ($source as $zone => $compte) {
                $total[(int) $zone] = ($total[(int) $zone] ?? 0) + (int) $compte;
            }
        }

        return $total;
    }

    /**
     * Les recherches ÉPUISÉES, pour les rattraper une par une (E31).
     *
     * @return Collection<int, AsapDispatchRequest>
     */
    public function recherchesEchouees(?Carbon $depuis = null): Collection
    {
        $depuis ??= Carbon::now()->subDays(7);

        return AsapDispatchRequest::query()
            ->where('status', AsapStatus::EXPIRED)
            ->where('created_at', '>=', $depuis)
            ->with(['booking:id,booking_reference,client_id,service_zone_id,trade_id', 'booking.clientUser:id,name,email'])
            ->latest('id')
            ->limit(100)
            ->get();
    }

    /** Combien d'offres ont été envoyées avant d'échouer — ce qui distingue « personne n'était là » de « tout le monde a refusé ». */
    public function diagnostiquer(AsapDispatchRequest $recherche): string
    {
        $offres = MissionAssignment::query()
            ->where('mission_id', $recherche->mission_id)
            ->count();

        if ($offres === 0) {
            return 'no_provider_found';
        }

        return 'all_declined';
    }
}
