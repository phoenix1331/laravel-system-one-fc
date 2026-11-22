<?php

declare(strict_types=1);

namespace App\Simulation;

use App\Engine\EngineConfig;
use App\Engine\MatchEngine;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Builds a match engine whose randomness is seeded, so any match can be replayed from its seed.
 */
final readonly class EngineFactory
{
    public function __construct(
        private EngineConfig $config,
    ) {}

    public function forSeed(int $seed): MatchEngine
    {
        return new MatchEngine($this->config, new Randomizer(new Mt19937($seed)));
    }

    public function runner(int $seed): MatchRunner
    {
        return new MatchRunner($this->forSeed($seed), maxSubstitutions: $this->config->maxSubstitutions);
    }
}
