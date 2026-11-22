<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Game screen
|--------------------------------------------------------------------------
*/

return [

    // Real seconds per match minute. 2 makes 90 minutes take about 3 minutes.
    'seconds_per_minute' => (float) env('GAME_SECONDS_PER_MINUTE', 2),

];
