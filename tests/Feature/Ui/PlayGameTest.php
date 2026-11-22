<?php

declare(strict_types=1);

use App\Engine\Mentality;
use App\Enums\Dugout;
use App\Enums\GameStatus;
use App\Games\GameRunner;
use App\Jobs\DecideForSystemOne;
use App\Livewire\Pages\PlayGame;
use App\Managers\Decision;
use App\Managers\SystemOneManager;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * A model answer that picks the given mentality with 0.6 probability.
 *
 * @return array<string, mixed>
 */
function modelReply(string $mentality = 'defensive', float $press = 0.2): array
{
    $answers = json_decode(file_get_contents(__DIR__.'/../SystemOne/fixtures/decision.json'), true);
    $answers['answers']['mentality']['choice'] = $mentality;
    $answers['answers']['mentality']['probabilities'] = array_fill_keys(['park_the_bus', 'defensive', 'balanced', 'attacking', 'all_out_attack'], 0.1);
    $answers['answers']['mentality']['probabilities'][$mentality] = 0.6;
    $answers['answers']['press_high']['noul'] = $press;

    return $answers;
}

function kickOff(int $seed = 42): Game
{
    return app(GameRunner::class)->start(
        Team::factory()->create(['name' => 'Bala Town']),
        Team::factory()->create(['name' => 'Penybont']),
        'd3:2b',
        $seed,
    );
}

it('shows the scoreboard and the paused kick-off decision', function (): void {
    Http::fake(['*' => Http::response(modelReply())]);
    $game = kickOff();

    $this->get(route('games.show', $game))
        ->assertOk()
        ->assertSeeLivewire(PlayGame::class)
        ->assertSee('Bala Town')
        ->assertSee('Penybont')
        ->assertSee('Your call, gaffer')
        ->assertSee('System One has decided. Hidden until you lock in.')
        ->assertSee('role="switch"', escape: false)
        ->assertDontSee('wire:poll', escape: false);
});

it('asks System One as soon as play pauses, using the game\'s model', function (): void {
    Http::fake(['*' => Http::response(modelReply())]);
    $game = kickOff();

    Http::assertSent(fn ($request): bool => ($request->data()['model'] ?? null) === 'd3:2b' && ($request->data()['state'] ?? null)['my_team'] === 'Penybont');

    expect($game->currentDecision(Dugout::Model)->mentality)->toBe(Mentality::Defensive)
        ->and($game->currentDecision(Dugout::Model)->manager)->toBe(SystemOneManager::class);
});

it('shows a thinking state and polls until the model answers', function (): void {
    Queue::fake();
    $game = kickOff();

    Livewire::test(PlayGame::class, ['game' => $game])
        ->assertSee('System One is thinking...')
        ->assertSeeHtml('wire:poll.1s');

    Queue::assertPushed(DecideForSystemOne::class, fn (DecideForSystemOne $job): bool => $job->gameId === $game->id && $job->decisionPoint === 1);
});

it('hides the model\'s choice until the player locks in, then reveals it with probabilities', function (): void {
    Http::fake(['*' => Http::response(modelReply('defensive', 0.8))]);
    $game = kickOff();

    Livewire::test(PlayGame::class, ['game' => $game])
        ->assertDontSee('How sure System One was')
        ->set('mentality', 'attacking')
        ->set('pressHigh', true)
        ->call('lockIn')
        ->assertSee('Decisions at')
        ->assertSee('How sure System One was')
        ->assertSee('Defensive')
        ->assertSee('0.60')
        ->assertSee('Pressing high')
        ->assertSee('Resume match');

    $human = $game->currentDecision(Dugout::Human);

    expect($human->mentality)->toBe(Mentality::Attacking)
        ->and($human->press_high)->toBeTrue();
});

it('waits for the model after the player locks in', function (): void {
    Queue::fake();
    $game = kickOff();

    Livewire::test(PlayGame::class, ['game' => $game])
        ->call('lockIn')
        ->assertSee('You are locked in. System One is thinking...')
        ->assertDontSee('Resume match')
        ->call('resume')
        ->assertSee('You are locked in.');

    expect($game->refresh()->status)->toBe(GameStatus::Deciding);
});

it('resumes play with both tactics applied and starts polling', function (): void {
    Http::fake(['*' => Http::response(modelReply('defensive'))]);
    config(['game.seconds_per_minute' => 0.5]);
    $game = kickOff();

    Livewire::test(PlayGame::class, ['game' => $game])
        ->set('mentality', 'attacking')
        ->call('lockIn')
        ->call('resume')
        ->assertSeeHtml('wire:poll.500ms="advance"')
        ->assertSee('Playing. Next decision point at 15')
        ->assertSee('Attacking · not pressing')
        ->assertSee('Defensive · not pressing')
        ->assertSet('mentality', 'attacking');

    $state = $game->refresh()->state();

    expect($game->status)->toBe(GameStatus::InProgress)
        ->and($state->home->tactics->mentality)->toBe(Mentality::Attacking)
        ->and($state->away->tactics->mentality)->toBe(Mentality::Defensive);
});

it('plays a minute on every poll and shows the commentary', function (): void {
    Http::fake(['*' => Http::response(modelReply('balanced'))]);
    $game = kickOff();

    $component = Livewire::test(PlayGame::class, ['game' => $game])
        ->call('lockIn')
        ->call('resume')
        ->call('advance')
        ->assertSee("1'")
        ->assertSee('kick off against Penybont');

    expect($game->refresh()->clock)->toBe("1'");
});

it('pauses at the next decision point with the model asked again', function (): void {
    Http::fake(['*' => Http::response(modelReply('balanced'))]);
    $game = kickOff(7);

    $component = Livewire::test(PlayGame::class, ['game' => $game])->call('lockIn')->call('resume');

    while (! $game->refresh()->isDeciding()) {
        $component->call('advance');
    }

    $component->assertSee('Your call, gaffer')
        ->assertSee('Play paused at')
        ->assertSee('Play paused. Both managers are making changes.');

    expect($game->decision_point)->toBe(2)
        ->and(Http::recorded(fn ($request): bool => str_ends_with($request->url(), '/v1/systemone')))->toHaveCount(2);
});

it('goes to the result at full time', function (): void {
    Http::fake(['*' => Http::response(modelReply('balanced'))]);
    $game = kickOff(3);
    $component = Livewire::test(PlayGame::class, ['game' => $game]);
    $guard = 0;

    while (! $game->refresh()->isFinished() && $guard++ < 500) {
        if ($game->isDeciding()) {
            $component->call('lockIn')->call('resume');

            continue;
        }

        $component->call('advance');
    }

    $component->assertRedirect(route('games.result', $game));
    $this->get(route('games.result', $game))->assertOk()->assertSee('Full time');
});

it('rejects a mentality that does not exist', function (): void {
    Queue::fake();
    $game = kickOff();

    Livewire::test(PlayGame::class, ['game' => $game])
        ->set('mentality', 'gegenpress')
        ->call('lockIn')
        ->assertHasErrors(['mentality']);

    expect($game->currentDecision(Dugout::Human))->toBeNull();
});

it('ignores a lock in while the clock is running', function (): void {
    Http::fake(['*' => Http::response(modelReply('balanced'))]);
    $game = kickOff();

    $component = Livewire::test(PlayGame::class, ['game' => $game])->call('lockIn')->call('resume');
    $component->call('lockIn');

    expect($game->decisions()->count())->toBe(2);
});

it('shows the latest concern only once the model\'s decision is revealed', function (): void {
    Http::fake(['*' => Http::response(modelReply('balanced'))]);
    $game = kickOff();

    Livewire::test(PlayGame::class, ['game' => $game])
        ->assertSee('- / 5')
        ->call('lockIn')
        ->assertSee('1 / 5');
});

it('marks a fallback decision in the reveal', function (): void {
    Queue::fake();
    $game = kickOff();
    app(GameRunner::class)->recordDecision($game, Dugout::Model, new Decision(Mentality::Balanced, fallbackUsed: true), 'SystemOneManager');

    Livewire::test(PlayGame::class, ['game' => $game])
        ->call('lockIn')
        ->assertSee('The model did not answer in time, so the rule-based bot chose for it.');
});

it('labels each stretch of the match', function (): void {
    Http::fake(['*' => Http::response(modelReply('balanced'))]);
    $game = kickOff(5);
    $component = Livewire::test(PlayGame::class, ['game' => $game]);
    $labels = [];

    while (! $game->refresh()->isFinished()) {
        if ($game->isDeciding()) {
            $component->call('lockIn')->call('resume');
        }

        $labels[] = $component->instance()->nextDecisionLabel();
        $component->call('advance');
    }

    expect(array_values(array_unique($labels)))->toContain("15'", "30'", 'half time', "60'", "75'", 'full time');
});
