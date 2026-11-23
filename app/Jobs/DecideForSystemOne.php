<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Dugout;
use App\Games\GameRunner;
use App\Managers\SystemOneManager;
use App\Models\Game;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Asks the System One manager for its decision in the background, so the page
 * stays responsive while the model thinks. It is queued twice per decision point:
 * when play resumes, to decide ahead on the state play will pause at, and when
 * play pauses, in case that answer is not in yet. The player sees "thinking..."
 * until one of them has stored a decision.
 */
class DecideForSystemOne implements ShouldQueue
{
    use Queueable;

    /** The manager falls back to the bot on failure, so a retry would only repeat the wait. */
    public int $tries = 1;

    public function __construct(
        public int $gameId,
        public int $decisionPoint,
    ) {}

    /**
     * Longer than the model timeout, so the fallback always gets to run.
     */
    public function timeout(): int
    {
        return (int) config('manager.timeout') + 30;
    }

    public function handle(GameRunner $runner, SystemOneManager $manager): void
    {
        // One model call per decision point, even if the ahead job is still thinking when play pauses.
        Cache::lock("system-one:{$this->gameId}:{$this->decisionPoint}", $this->timeout())
            ->get(fn () => $this->decide($runner, $manager));
    }

    private function decide(GameRunner $runner, SystemOneManager $manager): void
    {
        $game = Game::query()->find($this->gameId);

        if ($game === null || $game->decisionAt($this->decisionPoint, Dugout::Model) !== null) {
            return;
        }

        $manager = $manager->withModel($game->model);

        // Play has reached this decision point: decide on the match as it stands.
        if ($game->isDeciding() && $game->decision_point === $this->decisionPoint) {
            $decision = $manager->decide($runner->contextFor($game, Dugout::Model));
            $runner->recordDecision($game, Dugout::Model, $decision, SystemOneManager::class);

            return;
        }

        // Play is still heading there: decide on the state it will pause at.
        $atPause = $game->decision_point + 1 === $this->decisionPoint ? $runner->nextPause($game) : null;

        if ($atPause === null) {
            return;
        }

        $decision = $manager->decide($runner->contextFor($game, Dugout::Model, $atPause));
        $runner->recordDecisionAhead($game, $atPause, Dugout::Model, $decision, SystemOneManager::class);
    }
}
