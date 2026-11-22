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
            'instructions' => 'You manage my_team in a 90-minute football match. score.us is my_team\'s goals and score.them is the opponent\'s. A team that is behind late on needs goals and must take more risk; a team that is ahead late on should protect its lead; early on or when level, stay balanced. Which mentality should my_team play with now?',
            // Saying when each option is used, not only what it does, is what stopped d3:2b anchoring on "balanced".
            'criteria' => [
                'park_the_bus' => 'Everyone defends deep to protect a lead late in the match, accepting almost no chances',
                'defensive' => 'Sit deeper and stay compact to hold on to a lead, creating fewer chances but conceding fewer',
                'balanced' => 'An even split between attack and defence, the normal approach when the match is level or there is plenty of time left',
                'attacking' => 'Push extra players forward to create more chances when a goal is needed, leaving more space behind',
                'all_out_attack' => 'Throw everyone forward to chase a goal when losing near the end, leaving the defence badly exposed',
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

        // Only asked while the team has substitutions left (3 per match).
        'substitution' => [
            'type' => 'choice',
            'instructions' => 'my_team has subs_left substitutions left for the whole match. Should my_team make a substitution now? Most of the time the right answer is none; save substitutions for when tiredness or the score calls for one.',
            'criteria' => [
                'none' => 'Make no change and keep the substitutions for later',
                'fresh_legs' => 'Replace tired players with fresh ones when fitness has dropped low, restoring energy',
                'extra_striker' => 'Bring on an extra striker when a goal is needed late on, adding attack but weakening the defence',
                'extra_defender' => 'Bring on an extra defender to protect a lead late on, adding defence but blunting the attack',
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
