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
        return $this->withRandomizer($this->randomizer($seed));
    }

    public function randomizer(int $seed): Randomizer
    {
        return new Randomizer(new Mt19937($seed));
    }

    /**
     * An engine that carries on from a Randomizer saved part-way through a match.
     */
    public function withRandomizer(Randomizer $random): MatchEngine
    {
        return new MatchEngine($this->config, $random);
    }

    public function runner(int $seed): MatchRunner
    {
        return new MatchRunner($this->forSeed($seed), maxSubstitutions: $this->config->maxSubstitutions);
    }
}
