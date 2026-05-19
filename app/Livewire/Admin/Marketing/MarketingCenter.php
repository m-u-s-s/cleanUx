<?php

namespace App\Livewire\Admin\Marketing;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingOptOut;
use App\Models\MarketingSegment;
use App\Services\Marketing\CampaignEngine;
use App\Services\Marketing\SegmentEngine;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class MarketingCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'segments';  // segments | campaigns | recipients
    public string $search = '';

    public function recomputeSegment(int $segmentId): void
    {
        $segment = MarketingSegment::findOrFail($segmentId);
        $count = app(SegmentEngine::class)->compute($segment);
        $this->dispatch('toast', "Segment recomputed: {$count} membres", 'success');
    }

    public function scheduleCampaign(int $campaignId): void
    {
        $campaign = MarketingCampaign::findOrFail($campaignId);
        try {
            $count = app(CampaignEngine::class)->schedule($campaign);
            $this->dispatch('toast', "{$count} recipients planifiés", 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', "Erreur : {$e->getMessage()}", 'error');
        }
    }

    public function pauseCampaign(int $campaignId): void
    {
        app(CampaignEngine::class)->pause(MarketingCampaign::findOrFail($campaignId));
        $this->dispatch('toast', 'Campagne mise en pause', 'success');
    }

    public function cancelCampaign(int $campaignId): void
    {
        app(CampaignEngine::class)->cancel(MarketingCampaign::findOrFail($campaignId));
        $this->dispatch('toast', 'Campagne annulée', 'success');
    }

    public function render(): View
    {
        $kpis = [
            'segments_active' => MarketingSegment::query()->where('is_active', true)->count(),
            'campaigns_running' => MarketingCampaign::query()->running()->count(),
            'sent_7d' => MarketingCampaignRecipient::query()
                ->where('sent_at', '>=', now()->subDays(7))->count(),
            'opt_outs_total' => MarketingOptOut::count(),
        ];

        if ($this->tab === 'segments') {
            $items = MarketingSegment::query()
                ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
                ->orderByDesc('updated_at')
                ->paginate(15);
        } elseif ($this->tab === 'campaigns') {
            $items = MarketingCampaign::query()
                ->with('segment:id,name,code')
                ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
                ->orderByDesc('updated_at')
                ->paginate(15);
        } else {
            $items = MarketingCampaignRecipient::query()
                ->with(['campaign:id,name,code', 'user:id,email,name'])
                ->when($this->search, fn ($q) => $q->whereHas('user',
                    fn ($u) => $u->where('email', 'like', '%' . $this->search . '%')))
                ->orderByDesc('updated_at')
                ->paginate(20);
        }

        return view('livewire.admin.marketing.marketing-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
