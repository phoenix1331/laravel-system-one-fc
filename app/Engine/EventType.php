<?php

declare(strict_types=1);

namespace App\Engine;

enum EventType: string
{
    case KickOff = 'kick_off';
    case Shot = 'shot';
    case Goal = 'goal';
    case HalfTime = 'half_time';
    case FullTime = 'full_time';
}
