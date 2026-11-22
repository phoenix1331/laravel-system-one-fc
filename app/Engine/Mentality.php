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

    /**
     * One line for the tactics panel: what picking this does on the pitch.
     */
    public function description(): string
    {
        return match ($this) {
            self::ParkTheBus => 'Everyone behind the ball. Very hard to break down, very few chances.',
            self::Defensive => 'Sit deeper and stay compact. Fewer chances at both ends.',
            self::Balanced => 'An even split between attack and defence.',
            self::Attacking => 'Push extra players forward. More chances, more space behind.',
            self::AllOutAttack => 'Throw everyone forward and tire faster. Wide open at the back.',
        };
    }

    /**
     * Short label for the segmented control.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::ParkTheBus => 'Park bus',
            self::AllOutAttack => 'All out',
            default => $this->label(),
        };
    }
}
