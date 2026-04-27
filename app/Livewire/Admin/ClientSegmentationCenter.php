<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Analytics\ClientSegmentationService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ClientSegmentationCenter extends Component
{
    use WithPagination;

    public string $search = '';
    public string $segment = '';

    protected $paginationTheme = 'tailwind';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSegment(): void
    {
        $this->resetPage();
    }

    public function render(ClientSegmentationService $service): View
    {
        $clients = User::query()
            ->whereIn('role', ['client', 'entreprise'])
            ->withCount(['rendezVousClient', 'organizationSites'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate(10);

        $rows = $clients->getCollection()
            ->map(fn (User $client) => $service->segment($client))
            ->filter(function (array $row) {
                if ($this->segment === '') {
                    return true;
                }

                return in_array($this->segment, $row['labels'], true);
            })
            ->values();

        $clients->setCollection($rows);

        return view('livewire.admin.client-segmentation-center', [
            'clients' => $clients,
        ]);
    }
}