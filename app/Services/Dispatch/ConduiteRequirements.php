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

/**
 * QUI PEUT CONDUIRE POUR CE MÉTIER — et depuis quand la question se pose.
 *
 * Un métier de trajet exige un permis ; sous règles taxi, il exige en plus un véhicule récent et sa
 * carte grise. La règle vaut MÉTIER PAR MÉTIER, et c'est le point : un prestataire qui exerce la
 * peinture et la course, sans permis, doit continuer de recevoir la peinture. Un verrou global sur
 * le compte lui couperait tout — pour une pièce qui ne concerne que la moitié de son activité.
 *
 * L'INVARIANT VIT DANS LE SQL. Un filtre appliqué après la requête se rattrape par un repli le jour
 * où il vide la liste — c'est exactement ce qui était arrivé aux filtres de métier de ce dépôt, qui
 * rendaient la liste NON filtrée « pour ne pas bloquer le dispatch ». Une mission non pourvue est un
 * incident ; un conducteur sans permis au volant est autre chose.
 *
 * LA PÉRIODE DE GRÂCE N'EST PAS UNE FAIBLESSE, c'est ce qui rend la règle applicable. Le jour où un
 * administrateur coche « règles taxi » sur un métier existant, des dizaines de prestataires en
 * exercice deviennent non conformes d'un coup. Les couper le matin même, sans qu'ils aient été
 * prévenus ni aient eu le temps de photographier une carte grise, ne protège personne : ça vide le
 * métier de ses professionnels, et le premier signe pour eux serait un téléphone qui cesse de
 * sonner.
 */
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

    /**
     * La date à partir de laquelle l'exigence devient BLOQUANTE.
     *
     * `null` quand le métier n'exige rien. Sinon : la plus RÉCENTE des dates d'activation, plus le
     * délai de grâce. Prendre la plus récente est délibéré — cocher « règles taxi » sur un métier
     * déjà de trajet ajoute une pièce, et rouvrir un délai pour cette pièce-là est plus juste que
     * de la réclamer immédiatement au nom d'une règle activée l'an dernier.
     */
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

        /*
         * SANS DATE, LA RÈGLE EST BLOQUANTE TOUT DE SUITE.
         *
         * Le cas se produit sur les données antérieures à cette fonctionnalité, et sur tout métier
         * semé directement en base. Le défaut prudent est d'exiger : l'inverse — une grâce
         * éternelle faute d'origine — laisserait rouler indéfiniment sans permis, et personne ne
         * s'en apercevrait puisque rien ne s'afficherait nulle part.
         */
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

        /*
         * L'ÂGE DU VÉHICULE, EN SQL.
         *
         * Le comparer en PHP après la requête obligerait à charger tous les candidats pour en
         * écarter la moitié — et surtout, le repli habituel « ne rien filtrer si la liste est
         * vide » ferait passer les véhicules trop anciens exactement les jours de forte demande.
         */
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
