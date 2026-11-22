<?php

declare(strict_types=1);

namespace App\Managers;

use App\Engine\Mentality;
use App\Engine\Substitution;

/**
 * The player. Their decision is already made in the tactics panel; this hands it over like any other manager.
 */
final readonly class HumanManager implements Manager
{
    public function __construct(
        private Mentality $mentality,
        private bool $pressHigh,
        private ?Substitution $substitution = null,
    ) {}

    public function decide(DecisionContext $context): Decision
    {
        return new Decision($this->mentality, $this->pressHigh, $this->substitution);
    }
}
