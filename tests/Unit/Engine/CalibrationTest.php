<?php

declare(strict_types=1);
use App\Engine\TeamRatings;
use App\Engine\TeamState;

/*
 * Guards the constants in config/engine.php: if a change pushes average
 * goals or shots out of a realistic range, this fails.
 */

it('produces realistic goals and shots between evenly rated teams', function (): void {
    $matches = 1000;
    $goals = 0;
    $shots = 0;

    foreach (range(1, $matches) as $seed) {
        $engine = seededEngine($seed);
        $state = $engine->kickOff(evenTeam('Home'), evenTeam('Away'));

        while (! $state->isFinished()) {
            $engine->advance($state);
        }

        $goals += $state->home->goals + $state->away->goals;
        $shots += $state->home->shots + $state->away->shots;
    }

    expect($goals / $matches)->toBeBetween(2.2, 3.2)
        ->and($shots / $matches / 2)->toBeBetween(9.0, 14.0);
});

it('lets the strongest club beat the weakest most of the time', function (): void {
    $wins = 0;

    foreach (range(1, 300) as $seed) {
        $engine = seededEngine($seed);
        $state = $engine->kickOff(
            new TeamState('Favourites', new TeamRatings(84, 80, 78, 82)),
            new TeamState('Underdogs', new TeamRatings(57, 60, 58, 78)),
        );

        while (! $state->isFinished()) {
            $engine->advance($state);
        }

        $wins += $state->home->goals > $state->away->goals ? 1 : 0;
    }

    expect($wins / 300)->toBeBetween(0.6, 0.95);
});
