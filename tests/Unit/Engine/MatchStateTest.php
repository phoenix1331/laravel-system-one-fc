<?php

declare(strict_types=1);

use App\Engine\MatchState;
use App\Engine\Mentality;
use App\Engine\Phase;
use App\Engine\Side;
use App\Engine\Tactics;
use App\Engine\TeamRatings;
use App\Engine\TeamState;

function sampleMatch(): MatchState
{
    return new MatchState(
        new TeamState('Bala Town', new TeamRatings(70, 65, 68, 80), new Tactics(Mentality::Attacking, true)),
        new TeamState('Penybont', new TeamRatings(62, 60, 64, 75)),
    );
}

it('formats the clock with added time', function (): void {
    $state = sampleMatch();
    $state->minute = 45;
    $state->added = 2;

    expect($state->clock())->toBe("45+2'");

    $state->added = 0;
    $state->minute = 72;

    expect($state->clock())->toBe("72'");
});

it('reports 50 percent possession before kick-off', function (): void {
    expect(sampleMatch()->possessionLast15(Side::Home))->toBe(50);
});

it('keeps only the last 15 minutes for recent stats', function (): void {
    $state = sampleMatch();

    foreach (range(1, 20) as $minute) {
        $state->minute = $minute;
        $state->recordMinute($minute <= 10 ? Side::Home : Side::Away, shot: $minute % 5 === 0, goal: false);
    }

    // Minutes 6 to 20 remain: 5 home, 10 away. Away shots at 15 and 20, home shot at 10.
    expect($state->recent)->toHaveCount(15)
        ->and($state->possessionLast15(Side::Home))->toBe(33)
        ->and($state->possessionLast15(Side::Away))->toBe(67)
        ->and($state->shotsLast15(Side::Home))->toBe(1)
        ->and($state->shotsLast15(Side::Away))->toBe(2);
});

it('round trips through an array', function (): void {
    $state = sampleMatch();
    $state->phase = Phase::SecondHalf;
    $state->minute = 61;
    $state->addedTimeFirstHalf = 3;
    $state->home->goals = 2;
    $state->away->fitness = 61.5;
    $state->recordMinute(Side::Away, shot: true, goal: true);

    $copy = MatchState::fromArray(json_decode(json_encode($state->toArray()), true));

    expect($copy->toArray())->toBe($state->toArray())
        ->and($copy->score())->toBe('2-0')
        ->and($copy->team(Side::Home)->tactics->mentality)->toBe(Mentality::Attacking)
        ->and($copy->team(Side::Away)->name)->toBe('Penybont')
        ->and(Side::Home->opponent())->toBe(Side::Away)
        ->and(Side::Away->opponent())->toBe(Side::Home);
});
