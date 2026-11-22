<?php

declare(strict_types=1);

use App\Engine\EngineConfig;
use App\Engine\MatchEngine;
use App\Engine\TeamRatings;
use App\Engine\TeamState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Scenarios');

// Feature tests must never reach a real Ollama by accident: fake every request they make.
pest()->beforeEach(function (): void {
    Http::preventStrayRequests();
})->in('Feature');

/**
 * The engine config straight from config/engine.php, without booting Laravel.
 *
 * @param  array<string, mixed>  $overrides  merged recursively over the file, e.g. ['goal' => ['min' => 1.0]]
 */
function engineConfig(array $overrides = []): EngineConfig
{
    return EngineConfig::fromArray(array_replace_recursive(require __DIR__.'/../config/engine.php', $overrides));
}

/**
 * An engine with a seeded Randomizer, so every run with the same seed is identical.
 *
 * @param  array<string, mixed>  $overrides
 */
function seededEngine(int $seed = 42, array $overrides = []): MatchEngine
{
    return new MatchEngine(engineConfig($overrides), new Randomizer(new Mt19937($seed)));
}

function evenTeam(string $name = 'Even FC'): TeamState
{
    return new TeamState($name, new TeamRatings(attack: 65, midfield: 65, defence: 65, fitness: 75));
}
