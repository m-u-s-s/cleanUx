<?php

namespace App\Services\Onboarding;

use App\Models\ProviderOnboardingDocument;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Quels justificatifs ce prestataire doit-il fournir ?
 *
 * L'application n'en connaissait qu'un, écrit en dur : `identity_card`. Un électricien n'était
 * donc jamais invité à déposer sa certification, ni un peintre son attestation d'assurance — alors
 * que `ProviderOnboardingService::approveOnboarding()` EXIGE une assurance approuvée pour valider
 * un dossier. Le parcours menait ainsi à un dossier complet côté prestataire et impossible à
 * approuver côté admin, sans que rien ne l'indique nulle part.
 *
 * La liste se dérive donc des métiers déclarés : `trades.requires_insurance_proof` et
 * `trades.requires_certification` la portent déjà, le casier judiciaire est déclaré en
 * configuration (voir config/onboarding_documents.php).
 */
class ProviderDocumentRequirements
{
    /**
     * Les trois pièces qui valent identité. Une seule suffit — c'est aussi la règle
     * qu'applique `approveOnboarding()`.
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

        return $requirements;
    }

    /**
     * Types que le parcours d'onboarding doit voir déposés. Sert de source unique au validateur
     * de l'étape « justificatifs » comme à l'écran qui les demande.
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
     * Défensif sur le schéma comme le reste du module : le pivot `trade_user` est ce qu'écrit
     * l'inscription, mais un déploiement où il manquerait ne doit pas faire échouer le parcours —
     * on retombe alors sur la seule pièce d'identité, exigence universelle.
     *
     * @return Collection<int, Trade>
     */
    private function declaredTrades(User $user): Collection
    {
        if (! Schema::hasTable('trade_user')) {
            return collect();
        }

        return $user->trades()->get(['trades.id', 'trades.code', 'trades.requires_insurance_proof', 'trades.requires_certification']);
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
