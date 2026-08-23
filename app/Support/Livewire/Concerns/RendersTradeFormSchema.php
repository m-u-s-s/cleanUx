<?php

namespace App\Support\Livewire\Concerns;

use App\Models\Trade;
use App\Support\TradeFormSchema;

/** Trait Livewire — charge un schema de formulaire de Trade et expose l'état des réponses + utilitaires de validation et de calcul de prix. */
trait RendersTradeFormSchema
{
    /** Schema normalisé du Trade en cours, null si pas chargé ou Trade sans schema. */
    public ?array $tradeFormSchema = null;

    /** Réponses du client, keyé par field.key (initialisé aux defaults du schema). */
    public array $tradeFormAnswers = [];

    /** Charge le schema d'un Trade par ID. Réinitialise les answers aux defaults. */
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

    /** Computed property — delta de prix appliqué par les answers actuelles. */
    public function getTradeFormPriceDeltaProperty(): array
    {
        if (! $this->hasTradeFormSchema()) {
            return ['total' => 0.0, 'breakdown' => []];
        }
        $basePrice = (float) ($this->tradeFormBasePriceContext() ?? 0.0);

        return TradeFormSchema::computePriceDelta($this->tradeFormSchema, $this->tradeFormAnswers, $basePrice);
    }

    /** Hook surchargeable : base price utilisée pour calculer les pricings en "percent". */
    protected function tradeFormBasePriceContext(): ?float
    {
        return null;
    }
}
