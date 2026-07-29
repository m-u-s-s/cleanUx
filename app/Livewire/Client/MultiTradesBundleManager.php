<?php

namespace App\Livewire\Client;

use App\Models\MultiTradeBundle;
use App\Models\MultiTradeBundleItemQuote;
use App\Models\Trade;
use App\Services\Booking\ZoneCoverageService;
use App\Services\Bundles\MultiTradeBundleService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

class MultiTradesBundleManager extends Component
{
    #[Url]
    public string $tab = 'list';

    public string $bundleName = '';

    public string $bundleDescription = '';

    public string $postalCode = '';

    public string $city = '';

    public array $items = [];   // [{trade_id, label, description, duration_minutes, estimated_price_eur}, ...]

    public function mount(): void
    {
        $this->addItem();
        $this->addItem();
    }

    public function addItem(): void
    {
        $this->items[] = [
            'trade_id' => '',
            'label' => '',
            'description' => '',
            'duration_minutes' => 60,
            'estimated_price_eur' => 0,
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function createBundle(): void
    {
        $this->validate([
            'bundleName' => ['required', 'string', 'max:191'],
            'bundleDescription' => ['nullable', 'string', 'max:1000'],
            'postalCode' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.trade_id' => ['required', 'integer', 'exists:trades,id'],
            'items.*.label' => ['required', 'string', 'max:191'],
            'items.*.estimated_price_eur' => ['required', 'numeric', 'min:0'],
        ]);

        // Résout la zone de service depuis le code postal (optionnel) — sert à ne
        // solliciter que les prestataires couvrant la zone du chantier.
        $serviceZoneId = null;
        if ($this->postalCode !== '') {
            $coverage = app(ZoneCoverageService::class);
            $serviceZoneId = $coverage->resolveServiceZone(
                $coverage->resolvePostalCode($this->postalCode, $this->city ?: null)
            )?->id;
        }

        $payload = collect($this->items)->map(fn ($i) => [
            'trade_id' => (int) $i['trade_id'],
            'label' => $i['label'],
            'description' => $i['description'] ?? null,
            'duration_minutes' => (int) ($i['duration_minutes'] ?? 60),
            'estimated_price_cents' => (int) round(((float) $i['estimated_price_eur']) * 100),
        ])->toArray();

        try {
            app(MultiTradeBundleService::class)->createDraft(
                client: Auth::user(),
                name: $this->bundleName,
                items: $payload,
                description: $this->bundleDescription ?: null,
                serviceZoneId: $serviceZoneId,
            );
            $this->reset(['bundleName', 'bundleDescription', 'postalCode', 'city', 'items']);
            $this->mount();
            $this->tab = 'list';
            $this->dispatch('toast', 'Bundle créé en brouillon.', 'success');
        } catch (ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', $first ?? 'Échec.', 'error');
        }
    }

    public function startQuoting(int $bundleId): void
    {
        $bundle = MultiTradeBundle::query()
            ->where('id', $bundleId)
            ->where('client_user_id', Auth::id())
            ->first();
        if (! $bundle) {
            return;
        }
        try {
            app(MultiTradeBundleService::class)->startQuoting($bundle);
            $this->dispatch('toast', 'Demande de devis envoyée aux providers.', 'success');
        } catch (ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', $first ?? 'Échec.', 'error');
        }
    }

    public function acceptBundle(int $bundleId): void
    {
        $bundle = MultiTradeBundle::query()
            ->where('id', $bundleId)
            ->where('client_user_id', Auth::id())
            ->first();
        if (! $bundle) {
            return;
        }
        try {
            app(MultiTradeBundleService::class)->accept($bundle);
            $this->dispatch('toast', 'Bundle accepté — missions créées.', 'success');
        } catch (ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', $first ?? 'Échec.', 'error');
        }
    }

    public function cancelBundle(int $bundleId): void
    {
        $bundle = MultiTradeBundle::query()
            ->where('id', $bundleId)
            ->where('client_user_id', Auth::id())
            ->first();
        if (! $bundle) {
            return;
        }
        app(MultiTradeBundleService::class)->cancel($bundle, 'cancelled_by_client');
        $this->dispatch('toast', 'Bundle annulé.', 'success');
    }

    /**
     * P3 — Le client retient un devis reçu pour un item de son chantier.
     */
    public function selectQuote(int $quoteId): void
    {
        $quote = MultiTradeBundleItemQuote::query()
            ->where('id', $quoteId)
            ->where('status', MultiTradeBundleItemQuote::STATUS_SUBMITTED)
            ->whereHas('item.bundle', fn ($q) => $q->where('client_user_id', Auth::id()))
            ->first();

        if (! $quote) {
            $this->dispatch('toast', 'Devis introuvable ou déjà traité.', 'error');

            return;
        }

        try {
            app(MultiTradeBundleService::class)->selectQuote($quote);
            $this->dispatch('toast', 'Devis retenu.', 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', collect($e->errors())->flatten()->first() ?? 'Échec.', 'error');
        }
    }

    public function render(): View
    {
        $user = Auth::user();
        $bundles = MultiTradeBundle::query()
            ->where('client_user_id', $user->id)
            ->with([
                'items.trade:id,name,code',
                'items.provider:id,name',
                'items.quotes' => fn ($q) => $q->whereIn('status', [
                    MultiTradeBundleItemQuote::STATUS_SUBMITTED,
                    MultiTradeBundleItemQuote::STATUS_SELECTED,
                ])->with('provider:id,name'),
            ])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $trades = Trade::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('livewire.client.multi-trades-bundle-manager', [
            'bundles' => $bundles,
            'trades' => $trades,
        ])->layout('layouts.app');
    }
}
