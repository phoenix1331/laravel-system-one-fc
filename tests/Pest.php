<?php

declare(strict_types=1);

use App\Engine\EngineConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Scenarios');

/**
 * The engine config straight from config/engine.php, without booting Laravel.
 */
function engineConfig(): EngineConfig
{
    return EngineConfig::fromArray(require __DIR__.'/../config/engine.php');
}
