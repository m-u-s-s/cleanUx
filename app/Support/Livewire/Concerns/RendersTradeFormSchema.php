<?php

namespace App\Support\Livewire\Concerns;

use App\Models\Trade;
use App\Support\TradeFormSchema;

/**
 * Trait Livewire — charge un schema de formulaire de Trade et expose l'état
 * des réponses + utilitaires de validation et de calcul de prix.
 *
 * Convention d'utilisation côté composant :
 *
 *   class PrendreRendezVous extends Component {
 *     use RendersTradeFormSchema;
 *
 *     public function selectService(int $serviceId): void {
 *       // ... votre logique existante
 *       $this->loadTradeFormSchemaForTrade($service->trade_id);
 *     }
 *
 *     public function save(): void {
 *       $this->validate($this->tradeFormAnswersRules());
 *       Booking::create([..., 'trade_form_answers' => $this->tradeFormAnswers]);
 *     }
 *   }
 *
 * Côté Blade :
 *   <x-trade-form-fields :schema="$tradeFormSchema" wire-model-prefix="tradeFormAnswers" />
 */
trait RendersTradeFormSchema
{
    /** Schema normalisé du Trade en cours, null si pas chargé ou Trade sans schema. */
    public ?array $tradeFormSchema = null;

    /** Réponses du client, keyé par field.key (initialisé aux defaults du schema). */
    public array $tradeFormAnswers = [];

    /**
     * Charge le schema d'un Trade par ID. Réinitialise les answers aux defaults.
     * À appeler quand le service/trade sélectionné change.
     */
    protected function loadTradeFormSchemaForTrade(?int $tradeId): void
    {
        if (! $tradeId) {
            $this->tradeFormSchema = null;
            $this->tradeFormAnswers = [];
            return;
        }

        $trade = Trade::find($tradeId);
        if (! $trade || empty($trade->booking_form_schema)) {
            $this->tradeFormSchema = null;
            $this->tradeFormAnswers = [];
            return;
        }

        $result = TradeFormSchema::validate($trade->booking_form_schema);
        if (! $result['ok'] || empty($result['normalized']['fields'])) {
            $this->tradeFormSchema = null;
            $this->tradeFormAnswers = [];
            return;
        }

        $this->tradeFormSchema = $result['normalized'];
        $this->tradeFormAnswers = TradeFormSchema::defaultAnswers($result['normalized']);
    }

    /** Retourne true si un schema actif a au moins un champ. */
    public function hasTradeFormSchema(): bool
    {
        return ! empty($this->tradeFormSchema['fields'] ?? []);
    }

    /** Règles de validation Laravel pour les answers. */
    public function tradeFormAnswersRules(string $prefix = 'tradeFormAnswers'): array
    {
        if (! $this->hasTradeFormSchema()) {
            return [];
        }
        return TradeFormSchema::answerValidationRules($this->tradeFormSchema, $prefix);
    }

    /**
     * Computed property — delta de prix appliqué par les answers actuelles.
     * Accessible en vue via $this->tradeFormPriceDelta.
     */
    public function getTradeFormPriceDeltaProperty(): array
    {
        if (! $this->hasTradeFormSchema()) {
            return ['total' => 0.0, 'breakdown' => []];
        }
        $basePrice = (float) ($this->tradeFormBasePriceContext() ?? 0.0);
        return TradeFormSchema::computePriceDelta($this->tradeFormSchema, $this->tradeFormAnswers, $basePrice);
    }

    /**
     * Hook surchargeable : base price utilisée pour calculer les pricings
     * en "percent". Par défaut 0 (pas de pricing percent appliqué). Le
     * composant qui utilise le trait peut le surcharger pour pointer
     * vers son propre champ (ex: ServiceCatalog.base_price).
     */
    protected function tradeFormBasePriceContext(): ?float
    {
        return null;
    }
}
