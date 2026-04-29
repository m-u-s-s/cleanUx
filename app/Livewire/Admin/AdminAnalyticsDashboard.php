<?php

namespace App\Livewire\Admin;

use App\Services\Admin\AdminAnalyticsService;
use Livewire\Component;

class AdminAnalyticsDashboard extends Component
{
    public array $stats = [];

    public function mount(AdminAnalyticsService $service): void
    {
        $this->stats = $service->overview();
    }

    public function render()
    {
        return view('livewire.admin.admin-analytics-dashboard');
    }
}