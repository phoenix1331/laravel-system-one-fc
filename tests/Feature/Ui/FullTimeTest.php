<?php

declare(strict_types=1);

use App\Engine\Mentality;
use App\Engine\Substitution;
use App\Enums\Dugout;
use App\Enums\GameStatus;
use App\Games\GameRunner;
use App\Games\Verdict;
use App\Livewire\Pages\FullTime;
use App\Managers\Decision;
use App\Managers\RuleBasedManager;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

/**
 * A finished game with goals at the given minutes, e.g. ['human' => ["23'"], 'model' => ["61'", "79'"]].
 *
 * @param  array{human?: list<string>, model?: list<string>}  $goals
 */
function finishedGame(array $goals = []): Game
{
    $game = Game::factory()->create([
        'human_team_id' => Team::factory()->create(['name' => 'Bala Town']),
        'model_team_id' => Team::factory()->create(['name' => 'Penybont']),
        'seed' => 48213,
    ]);

    foreach ($goals as $side => $minutes) {
        foreach ($minutes as $minute) {
            $game->events()->create(['minute' => $minute, 'type' => 'goal', 'side' => $side, 'commentary' => 'GOAL!']);
        }
    }

    $game->update([
        'status' => GameStatus::Finished,
        'human_score' => count($goals['human'] ?? []),
        'model_score' => count($goals['model'] ?? []),
    ]);

    return $game;
}

/**
 * Record a decision point the way a real game would: open it, decide both sides.
 */
function logDecision(Game $game, int $point, Decision $human, Decision $model): void
{
    $game->update(['status' => GameStatus::Deciding, 'decision_point' => $point]);
    app(GameRunner::class)->recordDecision($game, Dugout::Human, $human, 'HumanManager');
    app(GameRunner::class)->recordDecision($game, Dugout::Model, $model, 'SystemOneManager');
    $game->update(['status' => GameStatus::Finished]);
}

it('shows the result, the verdict and the goals', function (): void {
    $game = finishedGame(['human' => ["23'"], 'model' => ["61'", "79'"]]);

    $this->get(route('games.result', $game))
        ->assertOk()
        ->assertSee('FULL TIME')
        ->assertSee('Bala Town')
        ->assertSee('System One wins this one')
        ->assertSee("Goals: Bala Town 23'. Penybont 61', 79'.")
        ->assertSee('Model d3:2b · seed 48213');
});

it('logs every model decision with its confidence, runner-up and concern', function (): void {
    $game = finishedGame();
    logDecision($game, 1, new Decision(Mentality::Balanced), new Decision(
        Mentality::Attacking,
        probabilities: ['mentality' => ['park_the_bus' => 0.01, 'defensive' => 0.05, 'balanced' => 0.21, 'attacking' => 0.64, 'all_out_attack' => 0.09]],
        concern: 3,
        responseMs: 12400,
    ));

    $this->get(route('games.result', $game))
        ->assertSee('Inside the System One dugout')
        ->assertSee('0.64')
        ->assertSee('Next: Balanced 0.21')
        ->assertSee('Concern 3 of 5')
        ->assertSee('12.4s');
});

it('marks fallback decisions and leaves them out of the response average', function (): void {
    $game = finishedGame();
    logDecision($game, 1, new Decision(Mentality::Balanced), new Decision(Mentality::Attacking, fallbackUsed: true));

    $this->get(route('games.result', $game))
        ->assertSee('Model timed out, bot chose')
        ->assertSee('No concern reading')
        ->assertSee('n/a');
});

it('shows the score at each decision home side first', function (): void {
    $game = finishedGame(['human' => ["10'"]]);
    $state = $game->state();
    $state->home->goals = 1;
    $game->update(['engine_state' => $state->toArray()]);
    logDecision($game, 1, new Decision(Mentality::Defensive), new Decision(Mentality::Attacking, concern: 4));

    $this->get(route('games.result', $game))->assertSee('1 - 0');
});

it('sends an unfinished game back to the match', function (): void {
    $game = Game::factory()->create();

    $this->get(route('games.result', $game))->assertRedirect(route('games.show', $game));
});

it('picks a headline from the score and when the decider went in', function (array $goals, string $headline): void {
    expect((new Verdict(finishedGame($goals)))->headline())->toBe($headline);
})->with([
    'goalless' => [[], 'Honours even, no goals'],
    'score draw' => [['human' => ["10'"], 'model' => ["50'"]], 'All square at the end'],
    'thrashing' => [['human' => ["5'", "20'", "60'"]], 'You ran riot'],
    'late human winner' => [['model' => ["30'"], 'human' => ["40'", "88'"]], 'You nicked it late'],
    'human win' => [['human' => ["30'"]], 'You beat System One'],
    'model thrashing' => [['model' => ["5'", "20'", "60'"]], 'System One ran the show'],
    'late model winner' => [['human' => ["23'"], 'model' => ["61'", "90+2'"]], 'System One takes it late'],
    'model win, late consolation' => [['model' => ["10'", "20'"], 'human' => ["85'"]], 'System One wins this one'],
]);

it('says when there were no goals', function (): void {
    expect((new Verdict(finishedGame()))->goals())->toBe('No goals.');
});

it('skips a decision point the model never answered', function (): void {
    $game = finishedGame();
    $game->update(['status' => GameStatus::Deciding, 'decision_point' => 1]);
    app(GameRunner::class)->recordDecision($game, Dugout::Human, new Decision(Mentality::Balanced), 'HumanManager');
    $game->update(['status' => GameStatus::Finished]);

    $this->get(route('games.result', $game))->assertSee('No decisions were recorded.');
});

it('shows the model-only decisions of a bot game home side first', function (): void {
    $game = finishedGame(['model' => ["10'"]]);
    $game->update(['status' => GameStatus::Deciding, 'decision_point' => 1, 'engine_state' => tap($game->state(), fn ($s) => $s->away->goals = 1)->toArray()]);
    app(GameRunner::class)->recordDecision($game, Dugout::Model, new Decision(Mentality::Defensive), RuleBasedManager::class);
    $game->update(['status' => GameStatus::Finished]);

    $this->get(route('games.result', $game))->assertSee('0 - 1');
});

it('starts a rematch with the same clubs and model but a new seed', function (): void {
    $game = finishedGame(['human' => ["10'"]]);

    Livewire\Livewire::test(FullTime::class, ['game' => $game])
        ->assertSee('Pick another club')
        ->call('rematch')
        ->assertRedirect(route('games.show', Game::query()->latest('id')->first()));

    $rematch = Game::query()->latest('id')->first();

    expect($rematch->id)->not->toBe($game->id)
        ->and($rematch->human_team_id)->toBe($game->human_team_id)
        ->and($rematch->model_team_id)->toBe($game->model_team_id)
        ->and($rematch->model)->toBe($game->model);
});

it('lists the substitutions each side made', function (): void {
    $game = finishedGame();
    logDecision($game, 1, new Decision(Mentality::Balanced, substitution: Substitution::FreshLegs), new Decision(Mentality::Attacking, substitution: Substitution::ExtraStriker));

    $this->get(route('games.result', $game))
        ->assertSee('Subs')
        ->assertSee('Fresh legs')
        ->assertSee('Extra striker');
});
