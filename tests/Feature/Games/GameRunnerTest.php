<?php

declare(strict_types=1);

use App\Engine\Mentality;
use App\Engine\Phase;
use App\Engine\Substitution;
use App\Enums\Dugout;
use App\Enums\GameStatus;
use App\Games\GameRunner;
use App\Managers\Decision;
use App\Managers\RuleBasedManager;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Team;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    // These tests record the model's decisions themselves.
    Queue::fake();
});

function runner(): GameRunner
{
    return app(GameRunner::class);
}

function startGame(int $seed = 42): Game
{
    return runner()->start(
        Team::factory()->create(['name' => 'Bala Town']),
        Team::factory()->create(['name' => 'Penybont']),
        'd3:2b',
        $seed,
    );
}

/**
 * Both dugouts make the same simple choice, then play resumes.
 */
function decideBoth(Game $game, Decision $human = new Decision(Mentality::Balanced), Decision $model = new Decision(Mentality::Balanced)): void
{
    runner()->recordDecision($game, Dugout::Human, $human, 'HumanManager');
    runner()->recordDecision($game, Dugout::Model, $model, RuleBasedManager::class);
    runner()->resume($game);
}

/**
 * Plays to full time, deciding at every pause. Returns the decision points seen.
 */
function playToEnd(Game $game): int
{
    $guard = 0;

    while (! $game->refresh()->isFinished() && $guard++ < 500) {
        if ($game->isDeciding()) {
            decideBoth($game);

            continue;
        }

        runner()->tick($game);
    }

    return $game->decision_point;
}

it('starts paused at the kick-off decision with the engine state saved', function (): void {
    $game = startGame();

    expect($game->status)->toBe(GameStatus::Deciding)
        ->and($game->decision_point)->toBe(1)
        ->and($game->seed)->toBe(42)
        ->and($game->model)->toBe('d3:2b')
        ->and($game->state()->phase)->toBe(Phase::PreMatch)
        ->and($game->humanTeam->name)->toBe('Bala Town')
        ->and($game->modelTeam->name)->toBe('Penybont');
});

it('does not play while waiting for decisions', function (): void {
    $game = startGame();

    expect(runner()->tick($game))->toBe([])
        ->and($game->refresh()->clock)->toBe("0'");
});

it('plays a minute per tick and stores the commentary', function (): void {
    $game = startGame();
    decideBoth($game);

    $events = runner()->tick($game);
    $game->refresh();

    expect($game->clock)->toBe("1'")
        ->and($events[0]->type)->toBe('kick_off')
        ->and($events[0]->commentary)->toContain('Bala Town')
        ->and($game->events()->count())->toBe(count($events));
});

it('pauses at the next decision point', function (): void {
    $game = startGame(7);
    decideBoth($game);

    while (! $game->refresh()->isDeciding()) {
        runner()->tick($game);
    }

    expect($game->decision_point)->toBe(2)
        ->and(in_array($game->clock, ["15'"], true) || $game->events()->where('type', 'goal')->exists())->toBeTrue();
});

it('plays a whole game to full time and keeps the score', function (): void {
    $game = startGame(3);

    $decisionPoints = playToEnd($game);
    $state = $game->state();

    expect($game->status)->toBe(GameStatus::Finished)
        ->and($decisionPoints)->toBeGreaterThanOrEqual(6)
        ->and($game->human_score)->toBe($state->home->goals)
        ->and($game->model_score)->toBe($state->away->goals)
        ->and($game->events()->where('type', 'full_time')->count())->toBe(1)
        ->and(runner()->tick($game))->toBe([]);
});

it('replays the same game from the same seed across saved steps', function (): void {
    $first = startGame(11);
    playToEnd($first);

    $second = runner()->start($first->humanTeam, $first->modelTeam, 'd3:2b', 11);
    playToEnd($second);

    expect($second->engine_state)->toBe($first->engine_state)
        ->and($second->events()->pluck('commentary')->all())->toBe($first->events()->pluck('commentary')->all());
});

it('records both decisions with the score from each side', function (): void {
    $game = startGame();

    $human = runner()->recordDecision($game, Dugout::Human, new Decision(Mentality::Attacking, true), 'HumanManager');
    $model = runner()->recordDecision($game, Dugout::Model, new Decision(
        Mentality::Defensive,
        probabilities: ['mentality' => ['defensive' => 0.6, 'balanced' => 0.4]],
        concern: 2,
        responseMs: 12000,
    ), 'SystemOneManager');

    expect($human->minute)->toBe("0'")
        ->and($human->score)->toBe('0-0')
        ->and($human->mentality)->toBe(Mentality::Attacking)
        ->and($model->probabilities['mentality']['defensive'])->toBe(0.6)
        ->and($model->toDecision()->concern)->toBe(2)
        ->and(runner()->bothDecided($game))->toBeTrue();
});

it('keeps the first decision if a dugout decides twice', function (): void {
    $game = startGame();

    runner()->recordDecision($game, Dugout::Human, new Decision(Mentality::Attacking), 'HumanManager');
    runner()->recordDecision($game, Dugout::Human, new Decision(Mentality::ParkTheBus), 'HumanManager');

    expect($game->decisions()->count())->toBe(1)
        ->and($game->currentDecision(Dugout::Human)->mentality)->toBe(Mentality::Attacking)
        ->and(runner()->bothDecided($game))->toBeFalse();
});

it('applies both decisions on resume and logs tactical changes', function (): void {
    $game = startGame();

    decideBoth($game, new Decision(Mentality::Attacking, true), new Decision(Mentality::Balanced));
    $game->refresh();
    $state = $game->state();

    expect($game->status)->toBe(GameStatus::InProgress)
        ->and($state->home->tactics->mentality)->toBe(Mentality::Attacking)
        ->and($state->home->tactics->pressHigh)->toBeTrue()
        ->and($game->events()->where('type', GameEvent::DECISION)->pluck('commentary')->all())
        ->toBe(['Bala Town go Attacking and press high.']);
});

it('will not resume until both managers have decided', function (): void {
    $game = startGame();
    runner()->recordDecision($game, Dugout::Human, new Decision(Mentality::Balanced), 'HumanManager');

    runner()->resume($game);
})->throws(LogicException::class, 'Both managers must decide');

it('refuses decisions while the clock is running', function (): void {
    $game = startGame();
    decideBoth($game);

    runner()->recordDecision($game->refresh(), Dugout::Human, new Decision(Mentality::Balanced), 'HumanManager');
})->throws(LogicException::class, 'not waiting for a decision');

it('builds each dugout\'s view of the match', function (): void {
    $game = startGame();

    expect(runner()->contextFor($game, Dugout::Human)->myTeam)->toBe('Bala Town')
        ->and(runner()->contextFor($game, Dugout::Model)->myTeam)->toBe('Penybont')
        ->and(runner()->contextFor($game, Dugout::Model)->subsLeft)->toBe(3);
});

it('labels the half-time decision HT', function (): void {
    $game = startGame(5);
    decideBoth($game);

    while ($game->refresh()->state()->phase !== Phase::HalfTime) {
        $game->isDeciding() ? decideBoth($game) : runner()->tick($game);
    }

    expect(runner()->recordDecision($game, Dugout::Human, new Decision(Mentality::Balanced), 'HumanManager')->minute)->toBe('HT');
});

it('rejects a corrupt random state', function (): void {
    $game = startGame();
    decideBoth($game);
    $game->refresh()->update(['random_state' => base64_encode(serialize('nope'))]);

    runner()->tick($game);
})->throws(LogicException::class, 'corrupt random state');

it('starts with a random seed when none is given', function (): void {
    $game = runner()->start(Team::factory()->create(), Team::factory()->create(), 'd3:2b');

    expect($game->seed)->toBeGreaterThan(0);
});

it('has a factory for finished games', function (): void {
    $game = Game::factory()->finished(2, 1)->create();

    expect($game->isFinished())->toBeTrue()
        ->and($game->human_score)->toBe(2);
});

it('applies substitutions on resume and logs them even without a tactical change', function (): void {
    $game = startGame();

    decideBoth($game, new Decision(Mentality::Balanced, substitution: Substitution::FreshLegs));
    $state = $game->refresh()->state();

    expect($state->home->substitutionsUsed)->toBe(1)
        ->and($game->events()->where('type', GameEvent::DECISION)->pluck('commentary')->all())
        ->toBe(['Bala Town go Balanced and hold their shape, bringing on fresh legs.']);
});

it('predicts every goal one tick before it is scored, and nothing else', function (): void {
    $game = startGame(3);
    $predicted = 0;
    $guard = 0;

    while (! $game->refresh()->isFinished() && $guard++ < 500) {
        if ($game->isDeciding()) {
            expect(runner()->upcomingGoal($game))->toBeNull();
            decideBoth($game);

            continue;
        }

        $upcoming = runner()->upcomingGoal($game);
        $goal = collect(runner()->tick($game))->firstWhere('type', 'goal');

        expect($upcoming)->toBe($goal?->side);
        $predicted += $upcoming === null ? 0 : 1;
    }

    expect($predicted)->toBe($game->human_score + $game->model_score)
        ->and($predicted)->toBeGreaterThan(0)
        ->and(runner()->upcomingGoal($game))->toBeNull();
});

it('predicts the state play will pause at, and nothing while paused', function (): void {
    $game = startGame(5);
    expect(runner()->nextPause($game))->toBeNull();

    decideBoth($game);
    $predicted = runner()->nextPause($game)->toArray();

    while (! $game->refresh()->isDeciding()) {
        runner()->tick($game);
    }

    expect($game->engine_state)->toBe($predicted);
});

it('predicts no pause when full time comes first', function (): void {
    $game = startGame(3);
    $guard = 0;

    while ((runner()->nextPause($game->refresh()) !== null || $game->isDeciding()) && $guard++ < 500) {
        if ($game->isDeciding()) {
            decideBoth($game);

            continue;
        }

        runner()->tick($game);
    }

    expect($game->status)->toBe(GameStatus::InProgress)
        ->and(runner()->nextPause($game))->toBeNull();
});
