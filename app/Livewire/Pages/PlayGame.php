<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Engine\MatchState;
use App\Engine\Mentality;
use App\Engine\Phase;
use App\Engine\Side;
use App\Enums\Dugout;
use App\Enums\GameStatus;
use App\Games\GameRunner;
use App\Managers\HumanManager;
use App\Models\Game;
use App\Models\GameDecision;
use App\Models\GameEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The match screen. The browser only polls and submits tactics; every outcome is decided on the server.
 *
 * @property-read MatchState $state
 * @property-read Collection<int, GameEvent> $events
 * @property-read GameDecision|null $humanDecision
 * @property-read GameDecision|null $modelDecision
 * @property-read GameDecision|null $lastRevealedModelDecision
 */
#[Layout('components.layouts.app')]
class PlayGame extends Component
{
    public Game $game;

    /** The tactics panel's current choice. */
    public string $mentality = 'balanced';

    public bool $pressHigh = false;

    public function mount(): void
    {
        $this->resetPanelToCurrentTactics();
    }

    /**
     * Called by wire:poll while the clock is running: plays one match minute.
     */
    public function advance(GameRunner $runner): void
    {
        $runner->tick($this->game);
        $this->refreshGame();

        if ($this->game->isFinished()) {
            $this->redirectRoute('games.result', $this->game, navigate: false);
        }
    }

    public function lockIn(GameRunner $runner): void
    {
        $this->validate([
            'mentality' => ['required', Rule::enum(Mentality::class)],
            'pressHigh' => ['boolean'],
        ]);

        if (! $this->game->isDeciding()) {
            return;
        }

        $manager = new HumanManager(Mentality::from($this->mentality), $this->pressHigh);
        $decision = $manager->decide($runner->contextFor($this->game, Dugout::Human));

        $runner->recordDecision($this->game, Dugout::Human, $decision, HumanManager::class);
        $this->refreshGame();
    }

    public function resume(GameRunner $runner): void
    {
        if (! $runner->bothDecided($this->game)) {
            return;
        }

        $runner->resume($this->game);
        $this->refreshGame();
        $this->resetPanelToCurrentTactics();
    }

    #[Computed]
    public function state(): MatchState
    {
        return $this->game->state();
    }

    /**
     * Newest first, as a live feed reads.
     *
     * @return Collection<int, GameEvent>
     */
    #[Computed]
    public function events(): Collection
    {
        return $this->game->events()->latest('id')->get();
    }

    #[Computed]
    public function humanDecision(): ?GameDecision
    {
        return $this->game->isDeciding() ? $this->game->currentDecision(Dugout::Human) : null;
    }

    /**
     * System One's choice at this decision point, once it has made one.
     */
    #[Computed]
    public function modelDecision(): ?GameDecision
    {
        return $this->game->isDeciding() ? $this->game->currentDecision(Dugout::Model) : null;
    }

    /**
     * The latest model decision the player is allowed to see: never the current one before they lock in.
     */
    #[Computed]
    public function lastRevealedModelDecision(): ?GameDecision
    {
        $visibleUpTo = $this->game->isDeciding() && $this->humanDecision === null
            ? $this->game->decision_point - 1
            : $this->game->decision_point;

        return $this->game->decisions()
            ->where('side', Dugout::Model)
            ->where('decision_point', '<=', $visibleUpTo)
            ->latest('decision_point')
            ->first();
    }

    public function render(): View
    {
        $state = $this->state;

        return view('livewire.pages.play-game', [
            'pollMs' => (int) round(1000 * (float) config('game.seconds_per_minute')),
            'half' => $this->halfLabel($state),
            'progress' => $this->progress($state),
            'goals' => $this->goalMarkers(),
            'possession' => $this->possession($state),
            'mentalities' => Mentality::cases(),
        ])->title("{$this->game->humanTeam->name} v {$this->game->modelTeam->name}");
    }

    private function refreshGame(): void
    {
        $this->game->refresh();
        unset($this->state, $this->events, $this->humanDecision, $this->modelDecision, $this->lastRevealedModelDecision);
    }

    private function resetPanelToCurrentTactics(): void
    {
        $tactics = $this->game->state()->home->tactics;
        $this->mentality = $tactics->mentality->value;
        $this->pressHigh = $tactics->pressHigh;
    }

    private function halfLabel(MatchState $state): string
    {
        return match ($state->phase) {
            Phase::PreMatch => 'Kick-off',
            Phase::FirstHalf => '1st half',
            Phase::HalfTime => 'Half time',
            Phase::SecondHalf => '2nd half',
            Phase::FullTime => 'Full time',
        };
    }

    /**
     * How far through the 90 minutes we are, 0 to 100.
     */
    private function progress(MatchState $state): float
    {
        return min(100, $state->minute / 90 * 100);
    }

    /**
     * @return list<array{position: float, dugout: Dugout, minute: string}>
     */
    private function goalMarkers(): array
    {
        return $this->events
            ->where('type', 'goal')
            ->map(fn (GameEvent $event): array => [
                'position' => min(100, (int) $event->minute / 90 * 100),
                'dugout' => $event->side ?? Dugout::Human,
                'minute' => $event->minute,
            ])
            ->values()
            ->all();
    }

    /**
     * Whole-match possession as percentages, 50/50 before kick-off.
     *
     * @return array{human: int, model: int}
     */
    private function possession(MatchState $state): array
    {
        $total = $state->home->possessionMinutes + $state->away->possessionMinutes;

        if ($total === 0) {
            return ['human' => 50, 'model' => 50];
        }

        $human = (int) round(100 * $state->team(Side::Home)->possessionMinutes / $total);

        return ['human' => $human, 'model' => 100 - $human];
    }

    public function isPlaying(): bool
    {
        return $this->game->status === GameStatus::InProgress;
    }

    /**
     * E.g. "60'", "half time" or "full time": the next scheduled pause.
     */
    public function nextDecisionLabel(): string
    {
        $state = $this->state;
        $firstHalf = in_array($state->phase, [Phase::PreMatch, Phase::FirstHalf], true);

        foreach ([15, 30] as $minute) {
            if ($firstHalf && $state->minute < $minute) {
                return "{$minute}'";
            }
        }

        if ($firstHalf) {
            return 'half time';
        }

        foreach ([60, 75] as $minute) {
            if ($state->minute < $minute) {
                return "{$minute}'";
            }
        }

        return 'full time';
    }
}
