<?php

namespace App\Livewire\Admin;

use App\Services\Analytics\OperationalQualityService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class OperationalQualityCenter extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';

    public function render(OperationalQualityService $quality): View
    {
        return view('livewire.admin.operational-quality-center', [
            'metrics' => $quality->metrics(
                $this->dateFrom ?: null,
                $this->dateTo ?: null,
            ),
        ]);
    }
}