<?php

declare(strict_types=1);

namespace App\Jobs;

use App\SystemOne\SystemOneClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Loads a decision model into Ollama while the player is still picking a club,
 * so the kick-off decision does not have to wait for a cold start.
 * Does nothing when the model is already loaded or Ollama cannot be reached.
 */
class WarmUpSystemOne implements ShouldQueue
{
    use Queueable;

    /** A failed warm-up costs nothing: the first decision warms up again. */
    public int $tries = 1;

    public function __construct(public string $model) {}

    public function handle(SystemOneClient $client): void
    {
        $client->withModel($this->model)->warmUp();
    }
}
