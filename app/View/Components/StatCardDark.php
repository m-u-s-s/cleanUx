<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class StatCardDark extends Component
{
    /**
     * Create a new component instance.
     */
    public $title, $value, $change, $changeColor;

    public function __construct($title, $value, $change = null, $changeColor = 'text-green-400')
    {
        $this->title = $title;
        $this->value = $value;
        $this->change = $change;
        $this->changeColor = $changeColor;
    }


    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.stat-card-dark');
    }
}
