<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\Mission;
use App\Models\ProviderPresence;
use Livewire\Component;

class AdminHomeDashboard extends Component
{
    public function render()
    {
        $today = now()->startOfDay();

        return view('livewire.admin.admin-home-dashboard', [
            'bookingsToday' => Booking::whereDate('created_at', $today)->count(),
            'activeMissions' => Mission::whereIn('status', ['planned', 'en_route', 'started'])->count(),
            'providersOnline' => ProviderPresence::where('status', 'online')->count(),
            'recentDisputes' => ComplaintCase::whereIn('status', ['open', 'assigned', 'investigating'])
                ->latest()
                ->take(5)
                ->get(),
            'recentBookings' => Booking::with('serviceCatalog:id,name')
                ->latest()
                ->take(10)
                ->get(['id', 'reference', 'status', 'service_catalog_id', 'created_at', 'scheduled_date']),
        ]);
    }
}
