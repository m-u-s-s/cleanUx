<?php

namespace App\Livewire\Client;

use App\Models\Trade;
use App\Services\Ai\PhotoQuoteEstimator;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class AiQuotePhoto extends Component
{
    use WithFileUploads;

    public ?int $tradeId = null;

    public $photo;

    public string $note = '';

    public ?array $result = null;

    public bool $estimating = false;

    public string $error = '';

    public function estimate(): void
    {
        $this->validate([
            'tradeId' => ['required', 'integer', 'exists:trades,id'],
            'photo' => ['required', 'image', 'max:5120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->estimating = true;
        $this->error = '';

        try {
            $trade = Trade::find($this->tradeId);
            if (! $trade) {
                $this->error = 'Métier introuvable.';

                return;
            }

            $base64 = base64_encode(file_get_contents($this->photo->getRealPath()));
            $result = app(PhotoQuoteEstimator::class)->estimateFromPhoto($base64, $trade, $this->note ?: null);

            if ($result === null) {
                $this->error = 'Service IA temporairement indisponible. Réessayez ou contactez le support.';

                return;
            }

            if (! ($result['success'] ?? false)) {
                $this->error = "L'IA n'a pas pu analyser cette photo : ".($result['reason'] ?? 'photo non pertinente');

                return;
            }

            $this->result = $result;
        } catch (\Throwable $e) {
            $this->error = 'Erreur : '.$e->getMessage();
        } finally {
            $this->estimating = false;
        }
    }

    public function reset_(): void
    {
        $this->reset(['result', 'error', 'photo', 'note']);
    }

    public function render(): View
    {
        $trades = Trade::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('livewire.client.ai-quote-photo', [
            'trades' => $trades,
        ])->layout('layouts.app');
    }
}
