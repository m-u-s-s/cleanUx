<?php

namespace App\Livewire\Admin\PricingV2;

use App\Models\AbPricingExperiment;
use App\Models\PriceQuote;
use App\Models\PricingRule;
use App\Models\ServiceCatalogV2;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class PricingCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'services';   // services | rules | quotes | experiments
    public string $search = '';

    public function render(): View
    {
        $kpis = [
            'services_active' => ServiceCatalogV2::query()->active()->count(),
            'rules_active' => PricingRule::query()->active()->count(),
            'quotes_7d' => PriceQuote::query()->where('quoted_at', '>=', now()->subDays(7))->count(),
            'experiments_running' => AbPricingExperiment::query()->running()->count(),
        ];

        if ($this->tab === 'services') {
            $items = ServiceCatalogV2::query()
                ->when($this->search, fn ($q) => $q->where(function ($w) {
                    $term = '%' . $this->search . '%';
                    $w->where('code', 'like', $term)->orWhere('name', 'like', $term);
                }))
                ->orderBy('code')
                ->paginate(20);
        } elseif ($this->tab === 'rules') {
            $items = PricingRule::query()
                ->when($this->search, fn ($q) => $q->where('code', 'like', '%' . $this->search . '%'))
                ->orderBy('priority')
                ->paginate(25);
        } elseif ($this->tab === 'quotes') {
            $items = PriceQuote::query()
                ->with('user:id,email')
                ->orderByDesc('quoted_at')
                ->paginate(25);
        } else {
            $items = AbPricingExperiment::query()
                ->orderByDesc('is_active')
                ->orderByDesc('starts_at')
                ->paginate(20);
        }

        return view('livewire.admin.pricing-v2.pricing-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
