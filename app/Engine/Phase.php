<?php

declare(strict_types=1);

namespace App\Engine;

enum Phase: string
{
    case PreMatch = 'pre_match';
    case FirstHalf = 'first_half';
    case HalfTime = 'half_time';
    case SecondHalf = 'second_half';
    case FullTime = 'full_time';
}
