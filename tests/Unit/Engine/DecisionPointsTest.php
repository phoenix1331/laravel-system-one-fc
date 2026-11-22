<?php

declare(strict_types=1);

use App\Engine\DecisionPoints;
use App\Engine\EventType;
use App\Engine\MatchState;
use App\Engine\Phase;

/**
 * Plays a whole match and returns the clock at every decision point.
 *
 * @param  array<string, mixed>  $overrides
 * @return list<string>
 */
function decisionClocks(int $seed, array $overrides = []): array
{
    $engine = seededEngine($seed, $overrides);
    $points = new DecisionPoints;
    $state = $engine->kickOff(evenTeam(), evenTeam());
    $clocks = [];
    $events = [];

    while (! $state->isFinished()) {
        if ($points->isDue($state, $events)) {
            $clocks[] = $state->phase === Phase::PreMatch ? 'KO' : ($state->phase === Phase::HalfTime ? 'HT' : $state->clock());
        }

        $events = $engine->advance($state);
    }

    return $clocks;
}

it('fires at kick-off, the set minutes and half time when nobody scores', function (): void {
    $noGoals = ['goal' => ['base' => 0.0, 'min' => 0.0, 'max' => 0.0], 'added_time' => ['min' => 2, 'max' => 2]];

    expect(decisionClocks(1, $noGoals))->toBe(['KO', "15'", "30'", 'HT', "60'", "75'"]);
});

it('fires in the minute after every goal', function (): void {
    $points = new DecisionPoints;
    $goalSteps = 0;
    $firedAfterGoal = 0;

    foreach (range(1, 10) as $seed) {
        $engine = seededEngine($seed);
        $state = $engine->kickOff(evenTeam(), evenTeam());

        while (! $state->isFinished()) {
            $events = $engine->advance($state);
            $scored = collect($events)->contains('type', EventType::Goal);
            $goalSteps += $scored ? 1 : 0;
            $firedAfterGoal += $scored && $points->isDue($state, $events) ? 1 : 0;
        }
    }

    expect($goalSteps)->toBeGreaterThan(0)
        ->and($firedAfterGoal)->toBe($goalSteps);
});

it('does not fire in added time without a goal', function (): void {
    $state = new MatchState(evenTeam(), evenTeam(), Phase::SecondHalf, minute: 90, added: 2);

    expect((new DecisionPoints)->isDue($state))->toBeFalse();
});

it('never fires after full time', function (): void {
    $state = new MatchState(evenTeam(), evenTeam(), Phase::FullTime, minute: 90);

    expect((new DecisionPoints)->isDue($state))->toBeFalse();
});

it('accepts custom decision minutes', function (): void {
    $state = new MatchState(evenTeam(), evenTeam(), Phase::FirstHalf, minute: 10);

    expect((new DecisionPoints([10]))->isDue($state))->toBeTrue()
        ->and((new DecisionPoints)->isDue($state))->toBeFalse();
});
