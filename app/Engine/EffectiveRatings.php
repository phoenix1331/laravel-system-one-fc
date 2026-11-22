<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Ratings for the current minute, after fitness, tactics and substitutions.
 */
final readonly class EffectiveRatings
{
    public function __construct(
        public float $attack,
        public float $midfield,
        public float $defence,
    ) {}
}
