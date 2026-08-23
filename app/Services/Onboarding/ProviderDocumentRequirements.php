<?php

namespace App\Services\Onboarding;

use App\Models\ProviderOnboardingDocument;
use App\Models\Trade;
use App\Models\User;
use App\Support\Domain\TradeRouteRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/** Quels justificatifs ce prestataire doit-il fournir ? */
class ProviderDocumentRequirements
{
    /**
     * Les trois pièces qui valent identité.
     *
     * @var array<int, string>
     */
    public const IDENTITY_TYPES = [
        ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
        ProviderOnboardingDocument::TYPE_PASSPORT,
        ProviderOnboardingDocument::TYPE_RESIDENCE_PERMIT,
    ];

    /**
     * @return array<int, array{type: string, label: string, help: string, required: bool, accepts: array<int, string>}>
     */
    public function for(User $user): array
    {
        $trades = $this->declaredTrades($user);

        $requirements = [$this->describe(ProviderOnboardingDocument::TYPE_IDENTITY_CARD, self::IDENTITY_TYPES)];

        if ($trades->contains(fn (Trade $trade) => (bool) $trade->requires_insurance_proof)) {
            $requirements[] = $this->describe(ProviderOnboardingDocument::TYPE_INSURANCE);
        }

        if ($trades->contains(fn (Trade $trade) => (bool) $trade->requires_certification)) {
            $requirements[] = $this->describe(ProviderOnboardingDocument::TYPE_DIPLOMA);
        }

        $sensitiveCodes = (array) Config::get('onboarding_documents.criminal_record_trades', []);
        if ($trades->contains(fn (Trade $trade) => in_array($trade->code, $sensitiveCodes, true))) {
            $requirements[] = $this->describe(ProviderOnboardingDocument::TYPE_CRIMINAL_RECORD);
        }

        // ON NE CONDUIT PAS SANS PERMIS.
        if ($trades->contains(fn (Trade $trade) => TradeRouteRules::estUnTrajet($trade))) {
            $requirements[] = $this->describe(ProviderOnboardingDocument::TYPE_DRIVING_LICENSE);
            $requirements[] = $this->describe(ProviderOnboardingDocument::TYPE_VEHICLE_INSURANCE);
        }

        // ET SOUS RÈGLES TAXI, on prouve aussi l'ÂGE du véhicule.
        if ($trades->contains(fn (Trade $trade) => (bool) $trade->taxi_rules)) {
            $requirements[] = $this->describe(ProviderOnboardingDocument::TYPE_VEHICLE_REGISTRATION);
        }

        return $requirements;
    }

    /**
     * Les métiers déclarés qui exigent un permis de conduire.
     *
     * @return Collection<int, Trade>
     */
    public function tradesExigeantLaConduite(User $user): Collection
    {
        return $this->declaredTrades($user)
            ->filter(fn (Trade $trade) => TradeRouteRules::estUnTrajet($trade) || (bool) $trade->taxi_rules)
            ->values();
    }

    /**
     * Types que le parcours d'onboarding doit voir déposés.
     *
     * @return array<int, string>
     */
    public function requiredTypesFor(User $user): array
    {
        return array_values(array_map(
            static fn (array $requirement): string => $requirement['type'],
            array_filter($this->for($user), static fn (array $r): bool => $r['required']),
        ));
    }

    /**
     * Un justificatif est-il satisfait ? La pièce d'identité accepte trois types, un seul suffit.
     *
     * @param  array<int, string>  $presentTypes
     */
    public function isSatisfied(string $type, array $presentTypes): bool
    {
        $accepted = in_array($type, self::IDENTITY_TYPES, true) ? self::IDENTITY_TYPES : [$type];

        return count(array_intersect($accepted, $presentTypes)) > 0;
    }

    /**
     * Métiers déclarés par le prestataire.
     *
     * @return Collection<int, Trade>
     */
    private function declaredTrades(User $user): Collection
    {
        if (! Schema::hasTable('trade_user')) {
            return collect();
        }

        // `questions` est chargée AVEC les métiers, et ce n'est pas une commodité : `TradeRouteRules` la relit sinon métier par métier, soit une requête par ligne de `trade_user`.
        return $user->trades()
            ->with(['questions' => fn ($q) => $q->select([
                'questions.id', 'questions.trade_id', 'questions.type',
                'questions.location_role', 'questions.is_active',
            ])])
            ->get([
                'trades.id', 'trades.code', 'trades.name',
                'trades.requires_insurance_proof', 'trades.requires_certification',
                'trades.taxi_rules', 'trades.taxi_rules_since', 'trades.route_rules_since',
            ]);
    }

    /**
     * @param  array<int, string>|null  $accepts
     * @return array{type: string, label: string, help: string, required: bool, accepts: array<int, string>}
     */
    private function describe(string $type, ?array $accepts = null): array
    {
        $labels = (array) Config::get("onboarding_documents.labels.{$type}", []);

        return [
            'type' => $type,
            'label' => (string) ($labels['label'] ?? $type),
            'help' => (string) ($labels['help'] ?? ''),
            'required' => true,
            'accepts' => $accepts ?? [$type],
        ];
    }
}
