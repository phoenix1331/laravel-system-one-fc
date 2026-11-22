<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Games\GameRunner;
use App\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * @property-read Collection<int, Team> $teams
 * @property-read Team|null $selectedTeam
 */
#[Layout('components.layouts.app')]
#[Title('Can you beat a System One manager?')]
class Home extends Component
{
    #[Validate('required|integer|exists:teams,id')]
    public ?int $teamId = null;

    /** Empty means a random opponent. */
    #[Validate('nullable|integer|exists:teams,id|different:teamId')]
    public ?int $opponentId = null;

    public function mount(): void
    {
        $this->teamId = $this->teams->first()?->id;
    }

    /**
     * @return Collection<int, Team>
     */
    #[Computed]
    public function teams(): Collection
    {
        return Team::query()->orderBy('name')->get();
    }

    #[Computed]
    public function selectedTeam(): ?Team
    {
        return $this->teams->firstWhere('id', $this->teamId);
    }

    public function kickOff(GameRunner $runner): void
    {
        $this->validate();

        $human = Team::query()->findOrFail($this->teamId);
        $model = $this->opponentId !== null
            ? Team::query()->findOrFail($this->opponentId)
            : Team::query()->whereKeyNot($human->id)->inRandomOrder()->firstOrFail();

        $game = $runner->start($human, $model, (string) config('manager.model'));

        $this->redirectRoute('games.show', $game, navigate: false);
    }

    public function render(): View
    {
        return view('livewire.pages.home', [
            'minutesToPlay' => (int) round(90 * (float) config('game.seconds_per_minute') / 60),
            'model' => (string) config('manager.model'),
        ]);
    }
}
