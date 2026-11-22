<?php

declare(strict_types=1);

use App\Engine\EffectiveRatings;
use App\Engine\EventType;
use App\Engine\MatchEngine;
use App\Engine\MatchEvent;
use App\Engine\MatchState;
use App\Engine\Mentality;
use App\Engine\Phase;
use App\Engine\Side;
use App\Engine\Tactics;

/**
 * Plays to full time, calling $between before every step so tests can change tactics mid-match.
 *
 * @return list<MatchEvent>
 */
function playMatch(MatchEngine $engine, MatchState $state, ?Closure $between = null): array
{
    $events = [];

    while (! $state->isFinished()) {
        $between?->__invoke($state);
        array_push($events, ...$engine->advance($state));
    }

    return $events;
}

it('replays the same match from the same seed', function (): void {
    $first = seededEngine(7)->kickOff(evenTeam('Home'), evenTeam('Away'));
    $firstEvents = playMatch(seededEngine(7), $first);

    $second = seededEngine(7)->kickOff(evenTeam('Home'), evenTeam('Away'));
    $secondEvents = playMatch(seededEngine(7), $second);

    expect($second->toArray())->toBe($first->toArray())
        ->and($secondEvents)->toEqual($firstEvents);
});

it('replays the same match when the same decisions are made', function (): void {
    $decisions = function (MatchState $state): void {
        if ($state->minute === 60 && $state->added === 0) {
            $state->home->tactics = new Tactics(Mentality::AllOutAttack, true);
        }
    };

    $engine = seededEngine(99);
    $first = $engine->kickOff(evenTeam(), evenTeam());
    playMatch($engine, $first, $decisions);

    $engine = seededEngine(99);
    $second = $engine->kickOff(evenTeam(), evenTeam());
    playMatch($engine, $second, $decisions);

    expect($second->toArray())->toBe($first->toArray());
});

it('plays a different match from a different seed', function (): void {
    $scores = collect(range(1, 20))->map(function (int $seed): string {
        $engine = seededEngine($seed);
        $state = $engine->kickOff(evenTeam(), evenTeam());
        playMatch($engine, $state);

        return $state->score().' '.$state->home->shots.'-'.$state->away->shots;
    });

    expect($scores->unique()->count())->toBeGreaterThan(1);
});

it('plays 90 minutes plus added time with the whistles in the right places', function (): void {
    $engine = seededEngine(3);
    $state = $engine->kickOff(evenTeam(), evenTeam());
    $events = playMatch($engine, $state);

    $possessionMinutes = $state->home->possessionMinutes + $state->away->possessionMinutes;
    $halfTime = collect($events)->firstWhere('type', EventType::HalfTime);
    $fullTime = collect($events)->firstWhere('type', EventType::FullTime);
    $kickOffs = collect($events)->where('type', EventType::KickOff)->values();

    expect($possessionMinutes)->toBe(90 + $state->addedTimeFirstHalf + $state->addedTimeSecondHalf)
        ->and($state->addedTimeFirstHalf)->toBeBetween(0, 4)
        ->and($state->addedTimeSecondHalf)->toBeBetween(0, 4)
        ->and($halfTime->minute)->toBe($state->addedTimeFirstHalf > 0 ? "45+{$state->addedTimeFirstHalf}'" : "45'")
        ->and($fullTime->payload['score'])->toBe($state->score())
        ->and($kickOffs->pluck('minute')->all())->toBe(["1'", "46'"])
        ->and($state->phase)->toBe(Phase::FullTime);
});

it('stops at half time until advanced again', function (): void {
    $engine = seededEngine(5, ['added_time' => ['min' => 2, 'max' => 2]]);
    $state = $engine->kickOff(evenTeam(), evenTeam());

    while ($state->phase !== Phase::HalfTime) {
        $engine->advance($state);
    }

    expect($state->clock())->toBe("45+2'");

    $engine->advance($state);

    expect($state->phase)->toBe(Phase::SecondHalf)
        ->and($state->clock())->toBe("46'");
});

it('refuses to play on after full time', function (): void {
    $engine = seededEngine();
    $state = $engine->kickOff(evenTeam(), evenTeam());
    playMatch($engine, $state);

    $engine->advance($state);
})->throws(LogicException::class, 'The match has finished.');

it('records goals with the score and counts them as shots', function (): void {
    // Every possession is a shot and every shot goes in.
    $engine = seededEngine(1, ['shot' => ['min' => 1.0, 'max' => 1.0], 'goal' => ['min' => 1.0, 'max' => 1.0]]);
    $state = $engine->kickOff(evenTeam(), evenTeam());

    $events = $engine->advance($state);
    $goal = $events[1];

    expect($events[0]->type)->toBe(EventType::KickOff)
        ->and($goal->type)->toBe(EventType::Goal)
        ->and($goal->payload['score'])->toBe($state->score())
        ->and($state->team($goal->side)->shots)->toBe(1)
        ->and($state->team($goal->side)->goals)->toBe(1)
        ->and($state->shotsLast15($goal->side))->toBe(1);
});

it('records a missed shot as a shot event', function (): void {
    $engine = seededEngine(1, ['shot' => ['min' => 1.0, 'max' => 1.0], 'goal' => ['min' => 0.0, 'max' => 0.0]]);
    $state = $engine->kickOff(evenTeam(), evenTeam());

    $shot = $engine->advance($state)[1];

    expect($shot->type)->toBe(EventType::Shot)
        ->and($state->team($shot->side)->goals)->toBe(0);
});

it('drains fitness every minute and never below the floor', function (): void {
    $engine = seededEngine(11);
    $state = $engine->kickOff(evenTeam(), evenTeam());
    $state->home->tactics = new Tactics(Mentality::AllOutAttack, true);
    playMatch($engine, $state);

    $minutes = 90 + $state->addedTimeFirstHalf + $state->addedTimeSecondHalf;

    expect($state->away->currentFitness())->toEqualWithDelta(75 - 0.4 * $minutes, 0.001)
        ->and($state->home->currentFitness())->toBe(20.0);
});

it('gives the stronger midfield more of the ball', function (): void {
    $engine = seededEngine();
    $strong = new EffectiveRatings(attack: 60, midfield: 75, defence: 60);
    $weak = new EffectiveRatings(attack: 60, midfield: 25, defence: 60);

    expect($engine->homePossessionProbability($strong, $weak))->toBe(0.75)
        ->and($engine->homePossessionProbability($weak, $strong))->toBe(0.25);
});

it('clamps shot and goal probabilities', function (float $attack, float $defence, float $shot, float $goal): void {
    // Pin the base rates so tuning config/engine.php does not break this test.
    $engine = seededEngine(overrides: ['shot' => ['base' => 0.25], 'goal' => ['base' => 0.10]]);
    $attacking = new EffectiveRatings($attack, 50, 50);
    $defending = new EffectiveRatings(50, 50, $defence);

    expect($engine->shotProbability($attacking, $defending))->toEqualWithDelta($shot, 0.0001)
        ->and($engine->goalProbability($attacking, $defending))->toEqualWithDelta($goal, 0.0001);
})->with([
    'even' => [60, 60, 0.25, 0.10],
    'hopeless attack' => [5, 90, 0.03, 0.03],
    'unstoppable attack' => [90, 10, 0.45, 0.25],
]);

it('gives the side in possession its possession minute', function (): void {
    $engine = seededEngine(2, ['added_time' => ['min' => 0, 'max' => 0]]);
    $state = $engine->kickOff(evenTeam(), evenTeam());
    $engine->advance($state);

    expect($state->home->possessionMinutes + $state->away->possessionMinutes)->toBe(1)
        ->and($state->recent[0]['possession'])->toBeIn([Side::Home->value, Side::Away->value]);
});
