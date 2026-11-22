<?php

declare(strict_types=1);

namespace App\Enums;

enum GameStatus: string
{
    /** The clock is running. */
    case InProgress = 'in_progress';

    /** Play is paused at a decision point until both managers have decided and the player resumes. */
    case Deciding = 'deciding';

    case Finished = 'finished';
}
