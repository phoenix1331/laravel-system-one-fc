<?php

declare(strict_types=1);

namespace App\Engine;

enum Mentality: string
{
    case ParkTheBus = 'park_the_bus';
    case Defensive = 'defensive';
    case Balanced = 'balanced';
    case Attacking = 'attacking';
    case AllOutAttack = 'all_out_attack';

    public function label(): string
    {
        return match ($this) {
            self::ParkTheBus => 'Park the bus',
            self::Defensive => 'Defensive',
            self::Balanced => 'Balanced',
            self::Attacking => 'Attacking',
            self::AllOutAttack => 'All out attack',
        };
    }
}
