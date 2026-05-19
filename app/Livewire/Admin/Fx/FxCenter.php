<?php

namespace App\Livewire\Admin\Fx;

use App\Models\Currency;
use App\Models\CurrencyConversion;
use App\Models\ExchangeRate;
use App\Services\Fx\FxService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class FxCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'rates';

    public function refreshAll(): void
    {
        try {
            $count = app(FxService::class)->refreshAll();
            $this->dispatch('toast', "Refreshed: {$count} new rates inserted.", 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', "Erreur : {$e->getMessage()}", 'error');
        }
    }

    public function render(): View
    {
        $kpis = [
            'currencies_active' => Currency::query()->active()->count(),
            'rates_total' => ExchangeRate::count(),
            'fallback_used_24h' => ExchangeRate::query()
                ->where('source', ExchangeRate::SOURCE_FALLBACK)
                ->where('fetched_at', '>=', now()->subDay())->count(),
            'conversions_7d' => CurrencyConversion::query()
                ->where('converted_at', '>=', now()->subDays(7))->count(),
        ];

        if ($this->tab === 'rates') {
            $items = ExchangeRate::query()
                ->orderByDesc('fetched_at')
                ->paginate(25);
        } elseif ($this->tab === 'conversions') {
            $items = CurrencyConversion::query()
                ->with(['user:id,email'])
                ->orderByDesc('converted_at')
                ->paginate(25);
        } else {
            $items = Currency::query()
                ->orderBy('sort_order')
                ->orderBy('code')
                ->paginate(50);
        }

        return view('livewire.admin.fx.fx-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
