<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Match engine constants
|--------------------------------------------------------------------------
|
| Every number the match engine uses lives here. The engine itself never
| reads config: App\Engine\EngineConfig::fromArray() turns this array into
| a typed value object that is passed in.
|
*/

return [

    // Fitness factor applied to every rating: base + scale * (fitness / 100).
    'fitness' => [
        'factor_base' => 0.7,
        'factor_scale' => 0.3,
        'drain_per_minute' => 0.4,
        'press_drain' => 0.3,
        'all_out_attack_drain' => 0.2,
        'floor' => 20,
    ],

    // The team in possession shoots with probability base * attack / defence.
    'shot' => [
        'base' => 0.25,
        'min' => 0.03,
        'max' => 0.45,
    ],

    // A shot scores with probability base * attack / defence.
    'goal' => [
        'base' => 0.10,
        'min' => 0.03,
        'max' => 0.25,
    ],

    // Pressing high multiplies effective midfield.
    'press' => [
        'midfield' => 1.10,
    ],

    // Multipliers on (attack, defence) for each mentality.
    'mentality' => [
        'park_the_bus' => ['attack' => 0.60, 'defence' => 1.30],
        'defensive' => ['attack' => 0.80, 'defence' => 1.15],
        'balanced' => ['attack' => 1.00, 'defence' => 1.00],
        'attacking' => ['attack' => 1.15, 'defence' => 0.90],
        'all_out_attack' => ['attack' => 1.30, 'defence' => 0.75],
    ],

    // Added time per half, picked by the seeded randomiser at kick-off.
    'added_time' => [
        'min' => 0,
        'max' => 4,
    ],

    // Substitution effects. Rating changes are permanent for the match.
    'substitutions' => [
        'max' => 3,
        'fresh_legs_fitness' => 15,
        'extra_striker' => ['attack' => 8, 'defence' => -5],
        'extra_defender' => ['attack' => -5, 'defence' => 8],
    ],

];
