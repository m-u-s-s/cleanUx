<?php

namespace App\Livewire;

use Livewire\Component;

class DesignSystem extends Component
{
    public function render()
    {
        return view('livewire.design-system')->layout('layouts.app');
    }
}
