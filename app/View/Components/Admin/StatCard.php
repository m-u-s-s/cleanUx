<?php

namespace App\View\Components\Admin;

use Illuminate\View\Component;
use Illuminate\View\View;

class StatCard extends Component
{
    public string $title;
    public string|int|float $value;

    public function __construct(string $title, string|int|float $value)
    {
        $this->title = $title;
        $this->value = $value;
    }

    public function render(): View
    {
        return view('components.admin.stat-card');
    }
}