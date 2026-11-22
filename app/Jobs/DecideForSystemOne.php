<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Dugout;
use App\Games\GameRunner;
use App\Managers\SystemOneManager;
use App\Models\Game;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Asks the System One manager for its decision in the background, so the page
 * stays responsive while the model thinks. The player sees "thinking..." until
 * this has stored a decision.
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
        $game = Game::query()->find($this->gameId);

        // The game moved on or the model already answered: nothing to do.
        if ($game === null || ! $game->isDeciding() || $game->decision_point !== $this->decisionPoint) {
            return;
        }

        if ($game->currentDecision(Dugout::Model) !== null) {
            return;
        }

        $manager = $manager->withModel($game->model);
        $decision = $manager->decide($runner->contextFor($game, Dugout::Model));

        $runner->recordDecision($game, Dugout::Model, $decision, SystemOneManager::class);
    }
}
