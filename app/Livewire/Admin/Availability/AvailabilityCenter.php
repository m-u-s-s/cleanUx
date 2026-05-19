<?php

namespace App\Livewire\Admin\Availability;

use App\Models\AvailabilityException;
use App\Models\AvailabilityHold;
use App\Models\AvailabilitySlot;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class AvailabilityCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';

    public function render(): View
    {
        $kpis = [
            'active_slots' => AvailabilitySlot::query()->where('is_active', true)->count(),
            'providers_with_slots' => AvailabilitySlot::query()
                ->where('is_active', true)
                ->distinct('provider_user_id')
                ->count('provider_user_id'),
            'exceptions_30d' => AvailabilityException::query()
                ->where('date', '>=', now()->subDays(30))->count(),
            'active_holds' => AvailabilityHold::query()->active()->count(),
        ];

        $providers = User::query()
            ->whereHas('availabilitySlots')
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->withCount(['availabilitySlots as slots_count' => fn ($q) => $q->where('is_active', true)])
            ->orderByDesc('slots_count')
            ->paginate(20);

        return view('livewire.admin.availability.availability-center', [
            'kpis' => $kpis,
            'providers' => $providers,
        ]);
    }
}
