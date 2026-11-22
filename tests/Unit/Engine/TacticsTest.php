<?php

declare(strict_types=1);

use App\Engine\Mentality;
use App\Engine\Substitution;
use App\Engine\Tactics;
use App\Engine\TeamRatings;
use App\Engine\TeamState;

function fullFitnessTeam(Tactics $tactics = new Tactics): TeamState
{
    return new TeamState('Test FC', new TeamRatings(attack: 60, midfield: 50, defence: 40, fitness: 100), $tactics);
}

it('applies the mentality multipliers to attack and defence', function (Mentality $mentality, float $attack, float $defence): void {
    $ratings = fullFitnessTeam(new Tactics($mentality))->effectiveRatings(engineConfig());

    expect($ratings->attack)->toEqualWithDelta(60 * $attack, 0.0001)
        ->and($ratings->defence)->toEqualWithDelta(40 * $defence, 0.0001)
        ->and($ratings->midfield)->toEqualWithDelta(50.0, 0.0001);
})->with([
    'park the bus' => [Mentality::ParkTheBus, 0.60, 1.30],
    'defensive' => [Mentality::Defensive, 0.80, 1.15],
    'balanced' => [Mentality::Balanced, 1.00, 1.00],
    'attacking' => [Mentality::Attacking, 1.15, 0.90],
    'all out attack' => [Mentality::AllOutAttack, 1.30, 0.75],
]);

it('boosts midfield by ten percent when pressing high', function (): void {
    $ratings = fullFitnessTeam(new Tactics(pressHigh: true))->effectiveRatings(engineConfig());

    expect($ratings->midfield)->toEqualWithDelta(55.0, 0.0001);
});

it('scales every rating by the fitness factor', function (): void {
    $team = new TeamState('Tired FC', new TeamRatings(60, 50, 40, 100), fitness: 50.0);

    $ratings = $team->effectiveRatings(engineConfig());

    // 0.7 + 0.3 * 0.5 = 0.85
    expect($team->fitnessFactor(engineConfig()))->toEqualWithDelta(0.85, 0.0001)
        ->and($ratings->attack)->toEqualWithDelta(51.0, 0.0001)
        ->and($ratings->midfield)->toEqualWithDelta(42.5, 0.0001)
        ->and($ratings->defence)->toEqualWithDelta(34.0, 0.0001);
});

it('adds substitution bonuses before the multipliers', function (): void {
    $team = fullFitnessTeam(new Tactics(Mentality::Attacking));
    $team->attackBonus = 8;
    $team->defenceBonus = -5;

    $ratings = $team->effectiveRatings(engineConfig());

    expect($ratings->attack)->toEqualWithDelta(68 * 1.15, 0.0001)
        ->and($ratings->defence)->toEqualWithDelta(35 * 0.90, 0.0001);
});

it('starts a team at its base fitness', function (): void {
    expect(fullFitnessTeam()->currentFitness())->toBe(100.0);
});

it('rejects ratings outside 1 to 100', function (int $value): void {
    new TeamRatings($value, 50, 50, 50);
})->with([0, 101])->throws(InvalidArgumentException::class);

it('labels every mentality and substitution', function (): void {
    expect(Mentality::ParkTheBus->label())->toBe('Park the bus')
        ->and(Mentality::AllOutAttack->label())->toBe('All out attack')
        ->and(array_map(fn (Mentality $m): string => $m->label(), Mentality::cases()))->toHaveCount(5)
        ->and(array_map(fn (Substitution $s): string => $s->label(), Substitution::cases()))
        ->toBe(['No change', 'Fresh legs', 'Extra striker', 'Extra defender']);
});

it('drains more fitness when pressing or going all out', function (Tactics $tactics, float $drain): void {
    expect(fullFitnessTeam($tactics)->fitnessDrain(engineConfig()))->toEqualWithDelta($drain, 0.0001);
})->with([
    'balanced' => [new Tactics, 0.4],
    'pressing' => [new Tactics(pressHigh: true), 0.7],
    'all out attack' => [new Tactics(Mentality::AllOutAttack), 0.6],
    'all out attack and pressing' => [new Tactics(Mentality::AllOutAttack, true), 0.9],
]);

it('never drains fitness below the floor', function (): void {
    $team = new TeamState('Tired FC', new TeamRatings(60, 50, 40, 100), new Tactics(Mentality::AllOutAttack, true), fitness: 20.5);

    $team->drainFitness(engineConfig());

    expect($team->currentFitness())->toBe(20.0);
});
