<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| System One manager
|--------------------------------------------------------------------------
|
| The questions the decision model answers at every decision point, all sent
| in one /v1/systemone request. The wording is the main lever on how the
| model behaves: describe what each option does, not just its name.
| Request and response shapes are in docs/systemone-api.md.
|
*/

return [

    'ollama_url' => env('OLLAMA_URL', 'http://localhost:11434'),

    'model' => env('SYSTEM_ONE_MODEL', 'd3:2b'),

    // Seconds. d3:2b on a CPU takes 11 to 13 seconds per decision.
    'timeout' => (int) env('SYSTEM_ONE_TIMEOUT', 30),

    'questions' => [

        'mentality' => [
            'type' => 'choice',
            'instructions' => 'You manage my_team in a 90-minute football match. Weigh the minute, the score (us against them), recent possession and shots, and both teams\' fitness. Which mentality should my_team play with from now on?',
            'criteria' => [
                'park_the_bus' => 'All players defend deep; accept very few chances to protect the score',
                'defensive' => 'Sit deeper and stay compact, creating fewer chances but conceding fewer',
                'balanced' => 'An even split between attack and defence',
                'attacking' => 'Push extra players forward to create more chances, leaving more space behind',
                'all_out_attack' => 'Throw everyone forward to chase a goal, leaving the defence badly exposed',
            ],
        ],

        'press_high' => [
            'type' => 'noul',
            'instructions' => 'Should my_team press high from now on? Pressing wins the ball back sooner but tires players faster, which hurts most when fitness is already low.',
            'criteria' => [
                'true' => 'Press high to win the ball back quickly, at the cost of tiring faster',
                'false' => 'Hold shape and conserve energy',
            ],
        ],

        // Display only: shown in the decision log, never used by the engine.
        'concern' => [
            'type' => 'score',
            'instructions' => 'How worried should the manager of my_team be about the final result right now?',
            'criteria' => [
                'Calm: the result looks safe',
                'Slightly uneasy: things are mostly going to plan',
                'Uneasy: the result could go either way',
                'Worried: the result is slipping away',
                'Very worried: likely to lose unless something changes',
            ],
        ],

    ],

];
