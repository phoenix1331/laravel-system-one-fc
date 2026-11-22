<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Models\Game;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class FullTime extends Component
{
    public Game $game;

    public function render(): View
    {
        return view('livewire.pages.full-time')->title('Full time');
    }
}
