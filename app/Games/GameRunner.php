<?php

declare(strict_types=1);

namespace App\Games;

use App\Engine\DecisionPoints;
use App\Engine\EngineConfig;
use App\Engine\EventType;
use App\Engine\MatchEngine;
use App\Engine\MatchState;
use App\Engine\Phase;
use App\Enums\Dugout;
use App\Enums\GameStatus;
use App\Jobs\DecideForSystemOne;
use App\Managers\Decision;
use App\Managers\DecisionContext;
use App\Models\Game;
use App\Models\GameDecision;
use App\Models\GameEvent;
use App\Models\Team;
use App\Simulation\Commentator;
use App\Simulation\EngineFactory;
use Illuminate\Support\Facades\DB;
use LogicException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Plays a stored game one step per request.
 *
 * The match state and the Randomizer are saved after every step, so a game
 * survives between browser polls and still replays exactly from its seed.
 */
final readonly class GameRunner
{
    public function __construct(
        private EngineFactory $engines,
        private EngineConfig $config,
        private Commentator $commentator,
        private DecisionPoints $decisionPoints = new DecisionPoints,
    ) {}

    /**
     * Create a game ready for kick-off. Kick-off is a decision point, so it starts paused.
     */
    public function start(Team $human, Team $model, string $modelName, ?int $seed = null): Game
    {
        $seed ??= random_int(1, 2_147_483_647);
        $random = $this->engines->randomizer($seed);
        $state = $this->engines->withRandomizer($random)->kickOff($human->toTeamState(), $model->toTeamState());

        $game = Game::query()->create([
            'human_team_id' => $human->id,
            'model_team_id' => $model->id,
            'seed' => $seed,
            'model' => $modelName,
            'status' => GameStatus::InProgress,
            'engine_state' => $state->toArray(),
            'random_state' => $this->saveRandomizer($random),
        ]);

        $this->openDecisionPoint($game);

        return $game;
    }

    /**
     * Play the next step of a running game. Does nothing while paused or finished.
     *
     * @return list<GameEvent>
     */
    public function tick(Game $game): array
    {
        return DB::transaction(function () use ($game): array {
            // Lock the row so two overlapping polls cannot play the same minute twice.
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);

            if ($game->status !== GameStatus::InProgress) {
                return [];
            }

            $random = $this->loadRandomizer($game);
            $state = $game->state();
            $events = $this->engine($random)->advance($state);

            $stored = [];

            foreach ($events as $event) {
                $stored[] = $game->events()->create([
                    'minute' => $event->minute,
                    'type' => $event->type->value,
                    'side' => $event->side !== null ? Dugout::fromSide($event->side) : null,
                    'commentary' => $this->commentator->describe($event, $state),
                    'payload' => $event->payload,
                ]);
            }

            $this->save($game, $state, $random);

            if ($state->isFinished()) {
                $game->update(['status' => GameStatus::Finished]);

                return $stored;
            }

            if ($this->decisionPoints->isDue($state, $events)) {
                $this->openDecisionPoint($game);
            }

            return $stored;
        });
    }

    /**
     * Which dugout scores in the next step, found by playing it on throwaway copies of the
     * state and Randomizer. Tactics only change at decision points, so while the clock runs
     * the next tick plays out exactly as this preview does.
     */
    public function upcomingGoal(Game $game): ?Dugout
    {
        if ($game->status !== GameStatus::InProgress) {
            return null;
        }

        $events = $this->engine($this->loadRandomizer($game))->advance($game->state());

        foreach ($events as $event) {
            if ($event->type === EventType::Goal && $event->side !== null) {
                return Dugout::fromSide($event->side);
            }
        }

        return null;
    }

    /**
     * The match from one dugout's side, as its manager sees it at a decision point:
     * the current one, or a pause found ahead of time with nextPause().
     */
    public function contextFor(Game $game, Dugout $dugout, ?MatchState $atPause = null): DecisionContext
    {
        return DecisionContext::fromMatch($atPause ?? $game->state(), $dugout->side(), $this->config->maxSubstitutions);
    }

    /**
     * Store one dugout's decision for the current decision point. Deciding twice keeps the first answer.
     */
    public function recordDecision(Game $game, Dugout $dugout, Decision $decision, string $manager): GameDecision
    {
        if (! $game->isDeciding()) {
            throw new LogicException('This game is not waiting for a decision.');
        }

        return $this->storeDecision($game, $game->decision_point, $game->state(), $dugout, $decision, $manager);
    }

    /**
     * Where the match will be when play next pauses, found by playing ahead on throwaway copies.
     * Tactics only change at decision points, so this is exactly the state the managers will see.
     * Null when the clock is stopped, or when full time comes first.
     */
    public function nextPause(Game $game): ?MatchState
    {
        if ($game->status !== GameStatus::InProgress) {
            return null;
        }

        $state = $game->state();
        $engine = $this->engine($this->loadRandomizer($game));

        while (! $state->isFinished()) {
            if ($this->decisionPoints->isDue($state, $engine->advance($state))) {
                return $state;
            }
        }

        return null;
    }

    /**
     * Store a decision made ahead of time, for the decision point play is heading towards.
     * It stays hidden like any other until that point is reached.
     */
    public function recordDecisionAhead(Game $game, MatchState $atPause, Dugout $dugout, Decision $decision, string $manager): GameDecision
    {
        return $this->storeDecision($game, $game->decision_point + 1, $atPause, $dugout, $decision, $manager);
    }

    public function bothDecided(Game $game): bool
    {
        return $game->currentDecision(Dugout::Human) !== null && $game->currentDecision(Dugout::Model) !== null;
    }

    /**
     * Apply both decisions and restart the clock.
     */
    public function resume(Game $game): void
    {
        $human = $game->currentDecision(Dugout::Human);
        $model = $game->currentDecision(Dugout::Model);

        if (! $game->isDeciding() || $human === null || $model === null) {
            throw new LogicException('Both managers must decide before play resumes.');
        }

        $state = $game->state();

        foreach ([$human, $model] as $row) {
            $team = $state->team($row->side->side());
            $decision = $row->toDecision();

            $substituted = $decision->substitution !== null && $team->substitute($decision->substitution, $this->config);

            if ($substituted || $decision->tactics() != $team->tactics) {
                $game->events()->create([
                    'minute' => $row->minute,
                    'type' => GameEvent::DECISION,
                    'side' => $row->side,
                    'commentary' => $this->commentator->describeDecision($row->side->side(), $decision, $state),
                ]);
            }

            $team->tactics = $decision->tactics();
        }

        $game->engine_state = $state->toArray();
        $game->status = GameStatus::InProgress;
        $game->save();

        // Start on the next pause now, so the answer is ready, or nearly, when play stops.
        DecideForSystemOne::dispatch($game->id, $game->decision_point + 1);
    }

    /**
     * The score from this dugout's side and the clock, stored once per dugout and decision point.
     */
    private function storeDecision(Game $game, int $decisionPoint, MatchState $state, Dugout $dugout, Decision $decision, string $manager): GameDecision
    {
        return $game->decisions()->firstOrCreate(
            ['decision_point' => $decisionPoint, 'side' => $dugout],
            [
                'minute' => $this->decisionMinute($state),
                'score' => $this->scoreFor($state, $dugout),
                'manager' => $manager,
                'mentality' => $decision->mentality,
                'press_high' => $decision->pressHigh,
                'substitution' => $decision->substitution,
                'concern' => $decision->concern,
                'probabilities' => $decision->probabilities,
                'fallback_used' => $decision->fallbackUsed,
                'response_ms' => $decision->responseMs,
            ],
        );
    }

    /**
     * Pause play and ask System One, unless it has already decided this point ahead of time.
     */
    private function openDecisionPoint(Game $game): void
    {
        $game->update([
            'status' => GameStatus::Deciding,
            'decision_point' => $game->decision_point + 1,
        ]);

        if ($game->currentDecision(Dugout::Model) === null) {
            DecideForSystemOne::dispatch($game->id, $game->decision_point)->afterCommit();
        }
    }

    private function save(Game $game, MatchState $state, Randomizer $random): void
    {
        $game->update([
            'engine_state' => $state->toArray(),
            'random_state' => $this->saveRandomizer($random),
            'clock' => $state->clock(),
            'human_score' => $state->home->goals,
            'model_score' => $state->away->goals,
        ]);
    }

    private function engine(Randomizer $random): MatchEngine
    {
        return $this->engines->withRandomizer($random);
    }

    private function saveRandomizer(Randomizer $random): string
    {
        return base64_encode(serialize($random));
    }

    private function loadRandomizer(Game $game): Randomizer
    {
        $random = unserialize(base64_decode($game->random_state), [
            'allowed_classes' => [Randomizer::class, Mt19937::class],
        ]);

        if (! $random instanceof Randomizer) {
            throw new LogicException("Game {$game->id} has a corrupt random state.");
        }

        return $random;
    }

    /**
     * "KO" before a ball is kicked, "HT" at the break, otherwise the clock.
     */
    private function decisionMinute(MatchState $state): string
    {
        return match ($state->phase) {
            Phase::PreMatch => "0'",
            Phase::HalfTime => 'HT',
            default => $state->clock(),
        };
    }

    /**
     * The score from this dugout's side, e.g. "1-2" when one down.
     */
    private function scoreFor(MatchState $state, Dugout $dugout): string
    {
        $us = $state->team($dugout->side())->goals;
        $them = $state->team($dugout->side()->opponent())->goals;

        return "{$us}-{$them}";
    }
}
