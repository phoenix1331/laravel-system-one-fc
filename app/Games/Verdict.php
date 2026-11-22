<?php

declare(strict_types=1);

namespace App\Games;

use App\Enums\Dugout;
use App\Models\Game;
use App\Models\GameEvent;

/**
 * The one-line headline and goal summary for the full-time screen.
 */
final readonly class Verdict
{
    /** A goal at or after this minute counts as late. */
    private const LATE = 80;

    public function __construct(
        private Game $game,
    ) {}

    public function headline(): string
    {
        $difference = $this->game->human_score - $this->game->model_score;

        return match (true) {
            $difference === 0 && $this->game->human_score === 0 => 'Honours even, no goals',
            $difference === 0 => 'All square at the end',
            $difference >= 3 => 'You ran riot',
            $difference > 0 && $this->lastGoalWasLateFor(Dugout::Human) => 'You nicked it late',
            $difference > 0 => 'You beat System One',
            $difference <= -3 => 'System One ran the show',
            $this->lastGoalWasLateFor(Dugout::Model) => 'System One takes it late',
            default => 'System One wins this one',
        };
    }

    /**
     * E.g. "Goals: Bala Town 23'. Penybont 61', 79'." or "No goals."
     */
    public function goals(): string
    {
        $goals = $this->game->events()->where('type', 'goal')->orderBy('id')->get();

        if ($goals->isEmpty()) {
            return 'No goals.';
        }

        $parts = [];

        foreach ([Dugout::Human, Dugout::Model] as $dugout) {
            $minutes = $goals->filter(fn (GameEvent $goal): bool => $goal->side === $dugout)->pluck('minute');

            if ($minutes->isNotEmpty()) {
                $team = $dugout === Dugout::Human ? $this->game->humanTeam : $this->game->modelTeam;
                $parts[] = "{$team->name} {$minutes->join(', ')}.";
            }
        }

        return 'Goals: '.implode(' ', $parts);
    }

    /**
     * True when the final goal of the match came late on and was scored by this dugout.
     */
    private function lastGoalWasLateFor(Dugout $dugout): bool
    {
        $last = $this->game->events()->where('type', 'goal')->latest('id')->first();

        return $last !== null && $last->side === $dugout && (int) $last->minute >= self::LATE;
    }
}
