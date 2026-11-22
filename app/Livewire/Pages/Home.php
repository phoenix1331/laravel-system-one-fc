<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Games\GameRunner;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
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

    public string $model = '';

    public function mount(): void
    {
        $this->teamId = $this->teams->first()?->id;
        $this->model = (string) config('manager.model');
    }

    /**
     * @return array<string, string>
     */
    public function models(): array
    {
        /** @var array<string, string> $models */
        $models = config('manager.models', []);

        // The configured default is always offered, even if it is not in the list.
        return [(string) config('manager.model') => (string) config('manager.model'), ...$models];
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
        $this->validate(['model' => ['required', Rule::in(array_keys($this->models()))]]);

        $human = Team::query()->findOrFail($this->teamId);
        $model = $this->opponentId !== null
            ? Team::query()->findOrFail($this->opponentId)
            : Team::query()->whereKeyNot($human->id)->inRandomOrder()->firstOrFail();

        $game = $runner->start($human, $model, $this->model);

        $this->redirectRoute('games.show', $game, navigate: false);
    }

    public function render(): View
    {
        return view('livewire.pages.home', [
            'record' => Game::record(),
            'minutesToPlay' => (int) round(90 * (float) config('game.seconds_per_minute') / 60),
            'defaultModel' => (string) config('manager.model'),
        ]);
    }
}
