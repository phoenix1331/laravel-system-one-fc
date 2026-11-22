<?php

declare(strict_types=1);

namespace App\Engine;

use InvalidArgumentException;

/**
 * A club's base ratings, each from 1 to 100.
 */
final readonly class TeamRatings
{
    public function __construct(
        public int $attack,
        public int $midfield,
        public int $defence,
        public int $fitness,
    ) {
        foreach (['attack' => $attack, 'midfield' => $midfield, 'defence' => $defence, 'fitness' => $fitness] as $name => $value) {
            if ($value < 1 || $value > 100) {
                throw new InvalidArgumentException("Rating {$name} must be between 1 and 100, got {$value}.");
            }
        }
    }

    /**
     * @return array{attack: int, midfield: int, defence: int, fitness: int}
     */
    public function toArray(): array
    {
        return [
            'attack' => $this->attack,
            'midfield' => $this->midfield,
            'defence' => $this->defence,
            'fitness' => $this->fitness,
        ];
    }

    /**
     * @param  array{attack: int, midfield: int, defence: int, fitness: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['attack'], $data['midfield'], $data['defence'], $data['fitness']);
    }
}
