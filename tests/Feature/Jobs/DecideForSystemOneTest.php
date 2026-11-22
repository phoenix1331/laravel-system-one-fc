<?php

declare(strict_types=1);

use App\Engine\Mentality;
use App\Enums\Dugout;
use App\Games\GameRunner;
use App\Jobs\DecideForSystemOne;
use App\Managers\Decision;
use App\Managers\SystemOneManager;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    $this->game = app(GameRunner::class)->start(Team::factory()->create(), Team::factory()->create(), 'd3:4b', 1);
});

it('asks the model the game was started with', function (): void {
    Http::fake(['*' => Http::response(json_decode(file_get_contents(__DIR__.'/../SystemOne/fixtures/decision.json'), true))]);

    (new DecideForSystemOne($this->game->id, 1))->handle(app(GameRunner::class), app(SystemOneManager::class));

    Http::assertSent(fn ($request): bool => ($request->data()['model'] ?? null) === 'd3:4b');
    expect($this->game->currentDecision(Dugout::Model)->concern)->toBe(1);
});

it('does nothing for a decision point that has passed', function (): void {
    (new DecideForSystemOne($this->game->id, 99))->handle(app(GameRunner::class), app(SystemOneManager::class));

    Http::assertNothingSent();
});

it('does nothing when the model has already decided', function (): void {
    app(GameRunner::class)->recordDecision($this->game, Dugout::Model, new Decision(Mentality::Balanced), 'SystemOneManager');

    (new DecideForSystemOne($this->game->id, 1))->handle(app(GameRunner::class), app(SystemOneManager::class));

    Http::assertNothingSent();
});

it('does nothing for a game that no longer exists', function (): void {
    (new DecideForSystemOne(999, 1))->handle(app(GameRunner::class), app(SystemOneManager::class));

    Http::assertNothingSent();
});

it('allows longer than the model timeout so the fallback can run', function (): void {
    config(['manager.timeout' => 30]);

    expect((new DecideForSystemOne(1, 1))->timeout())->toBe(60)
        ->and((new DecideForSystemOne(1, 1))->tries)->toBe(1);
});
