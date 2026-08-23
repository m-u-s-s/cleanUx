<?php

namespace App\Livewire\Provider;

use App\Models\MultiTradeBundleItemQuote;
use App\Services\Bundles\MultiTradeBundleService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

/** Bundle Marketplace P2 — page prestataire : voir ses demandes de devis de chantiers groupés et proposer son prix (concurrence). */
class BundleQuoteRequests extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public ?int $selectedId = null;

    public string $price = '';

    public string $message = '';

    private function ownActiveQuote(int $id): ?MultiTradeBundleItemQuote
    {
        return MultiTradeBundleItemQuote::query()
            ->where('id', $id)
            ->where('provider_user_id', Auth::id())
            ->active()
            ->first();
    }

    public function select(int $id): void
    {
        $quote = $this->ownActiveQuote($id);

        if (! $quote) {
            $this->dispatch('toast', 'Demande de devis introuvable ou clôturée.', 'error');

            return;
        }

        $this->selectedId = $quote->id;
        $this->price = $quote->price_cents ? number_format($quote->price_cents / 100, 2, '.', '') : '';
        $this->message = (string) $quote->message;
    }

    public function submit(): void
    {
        $this->validate([
            'price' => ['required', 'numeric', 'min:0.01'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $quote = $this->ownActiveQuote((int) $this->selectedId);

        if (! $quote) {
            $this->dispatch('toast', 'Demande de devis introuvable ou clôturée.', 'error');

            return;
        }

        try {
            app(MultiTradeBundleService::class)->submitQuote(
                $quote,
                Auth::user(),
                (int) round(((float) $this->price) * 100),
                $this->message ?: null,
            );
            $this->reset(['selectedId', 'price', 'message']);
            $this->dispatch('toast', 'Devis envoyé au client.', 'success');
        } catch (ValidationException $e) {
            $this->addError('price', collect($e->errors())->flatten()->first());
        }
    }

    public function withdraw(int $id): void
    {
        $quote = MultiTradeBundleItemQuote::query()
            ->where('id', $id)
            ->where('provider_user_id', Auth::id())
            ->first();

        if (! $quote) {
            return;
        }

        try {
            app(MultiTradeBundleService::class)->withdrawQuote($quote, Auth::user());
            if ($this->selectedId === $id) {
                $this->reset(['selectedId', 'price', 'message']);
            }
            $this->dispatch('toast', 'Devis retiré.', 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', collect($e->errors())->flatten()->first() ?? 'Échec.', 'error');
        }
    }

    public function render(): View
    {
        $requests = MultiTradeBundleItemQuote::query()
            ->where('provider_user_id', Auth::id())
            ->active()
            ->with(['item:id,bundle_id,trade_id,label,description', 'item.bundle:id,code,name', 'item.trade:id,name'])
            ->latest('created_at')
            ->paginate(10);

        return view('livewire.provider.bundle-quote-requests', [
            'requests' => $requests,
        ])->layout('layouts.app');
    }
}
