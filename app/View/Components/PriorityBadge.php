<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PriorityBadge extends Component
{
    public ?string $priority;

    public function __construct(?string $priority = null)
    {
        $this->priority = $priority;
    }

    public function render(): View
    {
        return view('components.priority-badge');
    }
}