<?php

namespace App\Services\Dispatch;

use App\Models\FleetVehicle;
use App\Models\ProviderOnboardingDocument;
use App\Models\Trade;
use App\Models\User;
use App\Support\Domain\TradeRouteRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/** QUI PEUT CONDUIRE POUR CE MÉTIER — et depuis quand la question se pose. */
class ConduiteRequirements
{
    /** Ce métier réclame-t-il quelque chose de ses conducteurs ? */
    public function sappliqueA(Trade $trade): bool
    {
        return TradeRouteRules::estUnTrajet($trade) || (bool) $trade->taxi_rules;
    }

    /**
     * Les pièces exigées pour CE métier.
     *
     * @return list<string>
     */
    public function typesExiges(Trade $trade): array
    {
        $types = [];

        if (TradeRouteRules::estUnTrajet($trade)) {
            $types[] = ProviderOnboardingDocument::TYPE_DRIVING_LICENSE;
            $types[] = ProviderOnboardingDocument::TYPE_VEHICLE_INSURANCE;
        }

        if ((bool) $trade->taxi_rules) {
            $types[] = ProviderOnboardingDocument::TYPE_VEHICLE_REGISTRATION;
        }

        return array_values(array_unique($types));
    }

    /** La date à partir de laquelle l'exigence devient BLOQUANTE. */
    public function bloquantDepuis(Trade $trade): ?Carbon
    {
        if (! $this->sappliqueA($trade)) {
            return null;
        }

        $dates = array_filter([
            TradeRouteRules::estUnTrajet($trade) ? $trade->route_rules_since : null,
            $trade->taxi_rules ? $trade->taxi_rules_since : null,
        ]);

        $delai = (int) Config::get('onboarding_documents.trade_requirements_grace_days', 30);

        // SANS DATE, LA RÈGLE EST BLOQUANTE TOUT DE SUITE.
        if ($dates === []) {
            return Carbon::now()->subCentury();
        }

        return collect($dates)->max()->copy()->addDays($delai);
    }

    /** L'exigence est-elle déjà opposable pour ce métier ? */
    public function estBloquant(Trade $trade, ?Carbon $maintenant = null): bool
    {
        $depuis = $this->bloquantDepuis($trade);

        return $depuis !== null && ($maintenant ?? Carbon::now())->greaterThanOrEqualTo($depuis);
    }

    /**
     * Restreint une requête de candidats aux seuls prestataires en règle pour CE métier.
     *
     * @param  Builder<User>  $query
     */
    public function appliquerAuxCandidats(Builder $query, Trade $trade): void
    {
        if (! $this->estBloquant($trade)) {
            return;
        }

        foreach ($this->typesExiges($trade) as $type) {
            $query->whereExists(function ($sous) use ($type) {
                $sous->selectRaw('1')
                    ->from('provider_onboarding_documents')
                    ->whereColumn('provider_onboarding_documents.user_id', 'users.id')
                    ->where('provider_onboarding_documents.document_type', $type)
                    // APPROUVÉE, pas seulement déposée : sans quoi n'importe quelle photo
                    // téléversée ouvrirait la porte le temps que quelqu'un la regarde.
                    ->where('provider_onboarding_documents.status', ProviderOnboardingDocument::STATUS_APPROVED)
                    ->where(function ($validite) {
                        // Une pièce sans date de fin reste valable — beaucoup n'en portent pas.
                        // Une pièce périmée, elle, ne vaut plus rien, et c'est précisément ce que
                        // la colonne `expires_at` permet enfin de dire.
                        $validite->whereNull('provider_onboarding_documents.expires_at')
                            ->orWhereDate('provider_onboarding_documents.expires_at', '>=', Carbon::now()->toDateString());
                    });
            });
        }

        if (! $trade->taxi_rules) {
            return;
        }

        // L'ÂGE DU VÉHICULE, EN SQL.
        $limite = Carbon::now()->subYears($this->limiteDAge())->toDateString();

        $query->whereExists(function ($sous) use ($limite) {
            $sous->selectRaw('1')
                ->from('fleet_vehicles')
                ->whereColumn('fleet_vehicles.current_provider_id', 'users.id')
                ->whereNot('fleet_vehicles.status', FleetVehicle::STATUS_RETIRED)
                ->whereNotNull('fleet_vehicles.registered_at')
                ->whereDate('fleet_vehicles.registered_at', '>=', $limite);
        });
    }

    /**
     * Ce qui manque à CE prestataire pour CE métier — en clair, pour le lui dire.
     *
     * @return list<string>
     */
    public function manquantsPour(User $user, Trade $trade): array
    {
        if (! $this->estBloquant($trade)) {
            return [];
        }

        $manquants = [];

        foreach ($this->typesExiges($trade) as $type) {
            $valide = ProviderOnboardingDocument::query()
                ->forUser($user->id)
                ->where('document_type', $type)
                ->where('status', ProviderOnboardingDocument::STATUS_APPROVED)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', Carbon::now()->toDateString());
                })
                ->exists();

            if (! $valide) {
                $manquants[] = (string) Config::get("onboarding_documents.labels.{$type}.label", $type);
            }
        }

        if ($trade->taxi_rules) {
            $conforme = FleetVehicle::query()
                ->where('current_provider_id', $user->id)
                ->whereNot('status', FleetVehicle::STATUS_RETIRED)
                ->whereNotNull('registered_at')
                ->whereDate('registered_at', '>=', Carbon::now()->subYears($this->limiteDAge())->toDateString())
                ->exists();

            if (! $conforme) {
                $manquants[] = sprintf('Véhicule de moins de %d ans déclaré', $this->limiteDAge());
            }
        }

        return $manquants;
    }

    private function limiteDAge(): int
    {
        return (int) Config::get('fleet_v2.taxi_rules.max_vehicle_age_years', 4);
    }
}
