<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\ModelStatus;
use App\SystemOne\SystemOneClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * A dot showing whether the game's decision model is loaded in Ollama.
 *
 * It polls on its own, slower than the match clock, so the play page's
 * minute-by-minute polls never wait on Ollama. Lazy, so the page itself never does either.
 */
#[Lazy]
class SystemOneStatus extends Component
{
    public string $model;

    public function placeholder(): string
    {
        return '<span class="inline-flex"><span class="size-2 rounded-full bg-line-strong" aria-hidden="true"></span></span>';
    }

    public function render(SystemOneClient $client): View
    {
        return view('livewire.system-one-status', [
            'status' => $client->withModel($this->model)->status(),
            'colours' => [
                ModelStatus::Ready->value => 'bg-online',
                ModelStatus::Asleep->value => 'bg-s1',
                ModelStatus::Offline->value => 'bg-offline',
            ],
        ]);
    }
}
