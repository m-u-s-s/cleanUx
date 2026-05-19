<?php

namespace App\Livewire\Admin;

use App\Support\Livewire\Concerns\RendersTradeFormSchema;
use App\Support\TradeFormSchema;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Aperçu interactif d'un schema de formulaire de Trade, côté admin.
 *
 * Reçoit en mount un schema (JSON décodé ou objet array). Permet à l'admin
 * de "jouer" avec le formulaire avant de sauver le schema sur le Trade,
 * et voir en live le delta de prix calculé.
 */
class TradeFormPreview extends Component
{
    use RendersTradeFormSchema;

    /** Schema brut en entrée (peut être JSON string ou array). */
    public mixed $schemaInput = null;

    /** Prix de base utilisé pour les pricings en percent. */
    public float $basePrice = 100.0;

    /** Erreurs de validation du schema lui-même (pas des answers). */
    public array $schemaErrors = [];

    public function mount(mixed $schemaInput = null, float $basePrice = 100.0): void
    {
        $this->basePrice = $basePrice;
        $this->loadSchemaInput($schemaInput);
    }

    public function updatedSchemaInput(): void
    {
        $this->loadSchemaInput($this->schemaInput);
    }

    public function loadSchemaInput(mixed $input): void
    {
        $this->schemaErrors = [];
        $this->tradeFormSchema = null;
        $this->tradeFormAnswers = [];

        if ($input === null || $input === '' || $input === []) {
            return;
        }

        // Si l'entrée est une string JSON, la décoder
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->schemaErrors = ['JSON invalide : '.json_last_error_msg()];
                return;
            }
            $input = $decoded;
        }

        $result = TradeFormSchema::validate($input);
        if (! $result['ok']) {
            $this->schemaErrors = $result['errors'];
            return;
        }

        $this->tradeFormSchema = $result['normalized'];
        $this->tradeFormAnswers = TradeFormSchema::defaultAnswers($result['normalized']);
    }

    protected function tradeFormBasePriceContext(): ?float
    {
        return $this->basePrice;
    }

    public function render(): View
    {
        return view('livewire.admin.trade-form-preview');
    }
}
