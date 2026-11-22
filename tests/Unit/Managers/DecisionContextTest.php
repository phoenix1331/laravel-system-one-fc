<?php

declare(strict_types=1);

use App\Engine\MatchState;
use App\Engine\Mentality;
use App\Engine\Phase;
use App\Engine\Side;
use App\Engine\Tactics;
use App\Engine\TeamRatings;
use App\Engine\TeamState;
use App\Managers\Decision;
use App\Managers\DecisionContext;

function seventyTwoMinutes(): MatchState
{
    $state = new MatchState(
        new TeamState('Bala Town', new TeamRatings(69, 68, 70, 72), fitness: 61.4),
        new TeamState('Penybont', new TeamRatings(68, 66, 67, 73), new Tactics(Mentality::Attacking, true), fitness: 74.0),
        Phase::SecondHalf,
        minute: 72,
    );
    $state->home->goals = 1;
    $state->away->goals = 1;
    $state->home->substitutionsUsed = 1;

    foreach (range(58, 72) as $minute) {
        $state->minute = $minute;
        $state->recordMinute($minute % 3 === 0 ? Side::Home : Side::Away, shot: $minute % 4 === 0, goal: false);
    }

    return $state;
}

it('builds the model state from the home side', function (): void {
    $context = DecisionContext::fromMatch(seventyTwoMinutes(), Side::Home);

    expect($context->toModelState())->toBe([
        'minute' => 72,
        'my_team' => 'Bala Town',
        'opponent' => 'Penybont',
        'score' => ['us' => 1, 'them' => 1],
        'possession_last_15' => 33,
        'shots_last_15' => ['us' => 2, 'them' => 2],
        'fitness' => ['us' => 61, 'them' => 74],
        'current_mentality' => 'balanced',
        'pressing' => false,
        'subs_left' => 2,
    ]);
});

it('flips the perspective for the away side', function (): void {
    $context = DecisionContext::fromMatch(seventyTwoMinutes(), Side::Away);

    expect($context->myTeam)->toBe('Penybont')
        ->and($context->possessionLast15)->toBe(67)
        ->and($context->shotsLast15For)->toBe(2)
        ->and($context->fitness)->toBe(74)
        ->and($context->tactics->mentality)->toBe(Mentality::Attacking)
        ->and($context->subsLeft)->toBe(3)
        ->and($context->goalDifference())->toBe(0);
});

it('counts added time in the minute and flags half time', function (): void {
    $state = seventyTwoMinutes();
    $state->phase = Phase::HalfTime;
    $state->minute = 45;
    $state->added = 3;

    $context = DecisionContext::fromMatch($state, Side::Home);

    expect($context->minute)->toBe(48)
        ->and($context->clock)->toBe("45+3'")
        ->and($context->isHalfTime)->toBeTrue();
});

it('converts between decisions and tactics', function (): void {
    $decision = Decision::fromTactics(new Tactics(Mentality::Defensive, true));

    expect($decision->tactics())->toEqual(new Tactics(Mentality::Defensive, true))
        ->and($decision->probabilities)->toBeNull()
        ->and($decision->fallbackUsed)->toBeFalse();
});
