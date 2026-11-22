<?php

declare(strict_types=1);

use App\Engine\Mentality;
use App\Engine\Substitution;
use App\Engine\Tactics;
use App\Managers\DecisionContext;
use App\Managers\RuleBasedManager;

function contextAt(int $minute, int $for, int $against, int $fitness = 70, int $opponentFitness = 70): DecisionContext
{
    return new DecisionContext(
        minute: $minute,
        clock: "{$minute}'",
        isHalfTime: false,
        myTeam: 'Bala Town',
        opponent: 'Penybont',
        goalsFor: $for,
        goalsAgainst: $against,
        possessionLast15: 50,
        shotsLast15For: 2,
        shotsLast15Against: 2,
        fitness: $fitness,
        opponentFitness: $opponentFitness,
        tactics: new Tactics,
        subsLeft: 3,
    );
}

it('picks a mentality from the score and the clock', function (int $minute, int $for, int $against, Mentality $expected): void {
    $decision = (new RuleBasedManager)->decide(contextAt($minute, $for, $against));

    expect($decision->mentality)->toBe($expected);
})->with([
    'kick-off' => [0, 0, 0, Mentality::Balanced],
    'losing early stays calm' => [30, 0, 1, Mentality::Balanced],
    'losing after an hour attacks' => [65, 0, 1, Mentality::Attacking],
    'losing by two after 70 goes all out' => [72, 0, 2, Mentality::AllOutAttack],
    'losing by one at 72 still just attacks' => [72, 1, 2, Mentality::Attacking],
    'losing after 80 goes all out' => [82, 1, 2, Mentality::AllOutAttack],
    'one up after 80 parks the bus' => [85, 1, 0, Mentality::ParkTheBus],
    'one up at 78 stays balanced' => [78, 1, 0, Mentality::Balanced],
    'two up after 75 sits deeper' => [76, 3, 1, Mentality::Defensive],
    'level late stays balanced' => [88, 2, 2, Mentality::Balanced],
]);

it('presses only when clearly fitter and not exhausted', function (int $fitness, int $opponentFitness, bool $expected): void {
    $decision = (new RuleBasedManager)->decide(contextAt(50, 0, 0, $fitness, $opponentFitness));

    expect($decision->pressHigh)->toBe($expected);
})->with([
    'fitter by five' => [70, 65, true],
    'fitter by four' => [70, 66, false],
    'less fit' => [60, 70, false],
    'fitter but exhausted' => [38, 25, false],
]);

it('leaves the model-only fields empty', function (): void {
    $decision = (new RuleBasedManager)->decide(contextAt(50, 0, 0));

    expect($decision->probabilities)->toBeNull()
        ->and($decision->concern)->toBeNull()
        ->and($decision->substitution)->toBeNull()
        ->and($decision->fallbackUsed)->toBeFalse();
});

it('makes substitutions when the game calls for them', function (int $minute, int $for, int $against, int $fitness, ?Substitution $expected): void {
    expect((new RuleBasedManager)->decide(contextAt($minute, $for, $against, $fitness))->substitution)->toBe($expected);
})->with([
    'chasing late' => [72, 0, 1, 70, Substitution::ExtraStriker],
    'protecting a one-goal lead' => [78, 1, 0, 70, Substitution::ExtraDefender],
    'tired after the hour' => [60, 1, 1, 45, Substitution::FreshLegs],
    'tired but early' => [40, 1, 1, 45, null],
    'nothing to change' => [60, 1, 1, 70, null],
]);

it('makes no substitution with none left', function (): void {
    $context = new DecisionContext(
        minute: 80, clock: "80'", isHalfTime: false, myTeam: 'A', opponent: 'B', goalsFor: 0, goalsAgainst: 1,
        possessionLast15: 50, shotsLast15For: 1, shotsLast15Against: 1, fitness: 40, opponentFitness: 50,
        tactics: new Tactics, subsLeft: 0,
    );

    expect((new RuleBasedManager)->decide($context)->substitution)->toBeNull();
});
