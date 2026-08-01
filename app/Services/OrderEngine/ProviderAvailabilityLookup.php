<?php

namespace App\Services\OrderEngine;

use App\Models\Trade;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use App\Services\GeolocationV2\DistanceCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Combien de prestataires, à quelle distance, et pour quand.
 *
 * Ce service ne sert qu'à une chose : permettre au parcours d'annoncer quelque chose de VRAI dès
 * que l'adresse est connue. Il ne réserve rien, ne verrouille rien, et n'a aucun effet de bord —
 * c'est une photographie, prise pour rassurer, et qui n'engage que ce qu'elle constate.
 *
 * Le compte est volontairement CONSERVATEUR. Un prestataire dont on ignore la position n'est pas
 * compté : mieux vaut annoncer moins que promettre une proximité qu'on ne peut pas établir. Le
 * premier client qui attend parce qu'on a gonflé le chiffre coûte plus cher que les dix qu'il
 * aurait convaincus.
 */
class ProviderAvailabilityLookup
{
    public function __construct(
        protected DistanceCalculator $distance,
        protected AvailabilityService $availability,
    ) {}

    public function forTrade(Trade $trade, float $lat, float $lng, ?int $radiusM = null): AvailabilitySnapshot
    {
        $radiusM ??= (int) Config::get('order_engine.availability_radius_m', 8000);

        $providers = $this->providersOf($trade);
        $locatable = $providers->filter(fn ($p) => $p->current_lat !== null && $p->current_lng !== null);

        // Sans aucune position connue, on ne dit rien : un compte non vérifiable n'est pas un compte.
        if ($locatable->isEmpty()) {
            return new AvailabilitySnapshot(0, $radiusM, null, 0, $this->neighbouringTrades($trade, $lat, $lng, $radiusM));
        }

        $withinRadius = $this->within($locatable, $lat, $lng, $radiusM);

        if ($withinRadius->isEmpty()) {
            /*
             * Impasse : jamais d'écran mort. On regarde ce qu'un rayon élargi donnerait et quels
             * métiers voisins sont, eux, couverts — de quoi proposer une suite plutôt qu'un constat.
             */
            $wider = (int) Config::get('order_engine.availability_wider_radius_m', 25000);

            return new AvailabilitySnapshot(
                providerCount: 0,
                radiusM: $radiusM,
                earliestAt: null,
                providersLocatable: $locatable->count(),
                nearbyTrades: $this->neighbouringTrades($trade, $lat, $lng, $wider),
                widerRadiusM: $wider,
                widerRadiusCount: $this->within($locatable, $lat, $lng, $wider)->count(),
            );
        }

        return new AvailabilitySnapshot(
            providerCount: $withinRadius->count(),
            radiusM: $radiusM,
            earliestAt: $this->earliestSlot($withinRadius, $trade),
            providersLocatable: $locatable->count(),
        );
    }

    /**
     * Prestataires actifs qui exercent ce métier.
     *
     * On lit `trade_user` : c'est la déclaration explicite de qui fait quoi. Se rabattre sur les
     * compétences textuelles du profil ferait apparaître dans « Plomberie » quiconque a écrit le
     * mot quelque part.
     *
     * @return Collection<int, object>
     */
    protected function providersOf(Trade $trade): Collection
    {
        return DB::table('trade_user')
            ->join('users', 'users.id', '=', 'trade_user.user_id')
            ->join('provider_profiles', 'provider_profiles.user_id', '=', 'users.id')
            ->where('trade_user.trade_id', $trade->id)
            ->where('provider_profiles.status', 'active')
            ->whereIn('users.role', [User::ROLE_PROVIDER, User::ROLE_EMPLOYE])
            ->select([
                'users.id',
                'provider_profiles.current_lat',
                'provider_profiles.current_lng',
            ])
            ->get();
    }

    /**
     * @param  Collection<int, object>  $providers
     * @return Collection<int, object>
     */
    protected function within(Collection $providers, float $lat, float $lng, int $radiusM): Collection
    {
        /*
         * Pré-filtre par boîte englobante avant le calcul exact.
         *
         * La distance haversine sur chaque ligne coûte cher dès que le nombre de prestataires
         * grandit, et cet appel arrive à CHAQUE frappe dans le champ d'adresse. Un degré de
         * latitude vaut ~111 km ; en longitude il rétrécit avec le cosinus de la latitude.
         */
        $latDelta = $radiusM / 111_320;
        $lngDelta = $radiusM / max(1, 111_320 * cos(deg2rad($lat)));

        return $providers
            ->filter(fn ($p) => abs((float) $p->current_lat - $lat) <= $latDelta
                && abs((float) $p->current_lng - $lng) <= $lngDelta)
            ->filter(fn ($p) => $this->distance->distanceMeters(
                $lat, $lng, (float) $p->current_lat, (float) $p->current_lng,
            ) <= $radiusM)
            ->values();
    }

    /**
     * Le premier créneau réellement libre, parmi les prestataires les plus proches.
     *
     * Interrogés en nombre BORNÉ : la disponibilité se calcule prestataire par prestataire, et
     * cet appel se déclenche à la saisie de l'adresse. Consulter cinquante agendas pour afficher
     * une phrase rassurante ferait ramer l'écran qu'elle est censée servir.
     *
     * Rend `null` plutôt qu'une date approximative — mieux vaut taire l'heure que l'inventer.
     *
     * @param  Collection<int, object>  $providers
     */
    protected function earliestSlot(Collection $providers, Trade $trade): ?Carbon
    {
        $sampleSize = (int) Config::get('order_engine.availability_sample_size', 5);
        $horizonDays = (int) Config::get('order_engine.availability_horizon_days', 7);
        $duration = max(30, (int) ($trade->estimated_duration_min ?? 60));

        $from = Carbon::now();
        $to = $from->copy()->addDays($horizonDays);
        $earliest = null;

        foreach ($providers->take($sampleSize) as $row) {
            $provider = User::find($row->id);

            if (! $provider) {
                continue;
            }

            try {
                foreach ($this->availability->getAvailableWindows($provider, $from, $to) as $window) {
                    $start = $this->windowStart($window);

                    if (! $start || ! $this->windowFits($window, $duration)) {
                        continue;
                    }

                    if ($earliest === null || $start->lt($earliest)) {
                        $earliest = $start;
                    }
                }
            } catch (\Throwable $e) {
                /*
                 * Soft-fail volontaire : la disponibilité est un CONFORT d'affichage. Un agenda
                 * illisible ne doit pas empêcher quelqu'un de commander — il doit juste priver la
                 * page d'une phrase rassurante.
                 */
                Log::warning('[order_engine] agenda prestataire illisible', [
                    'provider_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $earliest;
    }

    /** Le format des fenêtres appartient au module Disponibilité : on le lit sans le présumer. */
    protected function windowStart(mixed $window): ?Carbon
    {
        $raw = is_array($window) ? ($window['start'] ?? $window['starts_at'] ?? null) : null;

        if ($raw === null) {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Une fenêtre trop courte pour la prestation n'en est pas une. */
    protected function windowFits(mixed $window, int $durationMin): bool
    {
        if (! is_array($window)) {
            return false;
        }

        $end = $window['end'] ?? $window['ends_at'] ?? null;
        $start = $this->windowStart($window);

        if ($start === null || $end === null) {
            // Sans fin déclarée, on ne peut rien affirmer sur la durée : on accepte, la page
            // n'annonce qu'une possibilité, pas un engagement.
            return true;
        }

        try {
            return $start->diffInMinutes(Carbon::parse($end)) >= $durationMin;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Les métiers voisins qui, eux, sont couverts.
     *
     * C'est la porte de sortie de l'impasse : plutôt qu'un « aucun prestataire », on propose ce qui
     * existe réellement autour. Limité au même secteur — proposer de la plomberie à qui cherche un
     * élagueur ne serait pas une suggestion, mais un aveu d'impuissance déguisé.
     *
     * @return list<array{trade_id: int, name: string, slug: string, provider_count: int}>
     */
    protected function neighbouringTrades(Trade $trade, float $lat, float $lng, int $radiusM): array
    {
        if (! $trade->sector_id) {
            return [];
        }

        return Trade::query()
            ->where('sector_id', $trade->sector_id)
            ->where('id', '!=', $trade->id)
            ->where('is_active', true)
            ->get()
            ->map(function (Trade $neighbour) use ($lat, $lng, $radiusM) {
                $locatable = $this->providersOf($neighbour)
                    ->filter(fn ($p) => $p->current_lat !== null && $p->current_lng !== null);

                return [
                    'trade_id' => $neighbour->id,
                    'name' => $neighbour->name,
                    'slug' => $neighbour->slug,
                    'provider_count' => $this->within($locatable, $lat, $lng, $radiusM)->count(),
                ];
            })
            ->filter(fn (array $row) => $row['provider_count'] > 0)
            ->sortByDesc('provider_count')
            ->take(3)
            ->values()
            ->all();
    }
}
