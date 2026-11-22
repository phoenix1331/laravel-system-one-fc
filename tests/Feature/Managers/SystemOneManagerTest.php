<?php

declare(strict_types=1);

use App\Engine\Mentality;
use App\Engine\Tactics;
use App\Managers\DecisionContext;
use App\Managers\SystemOneManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function losingLate(): DecisionContext
{
    return new DecisionContext(
        minute: 82, clock: "82'", isHalfTime: false, myTeam: 'Bala Town', opponent: 'Penybont',
        goalsFor: 0, goalsAgainst: 1, possessionLast15: 40, shotsLast15For: 1, shotsLast15Against: 3,
        fitness: 55, opponentFitness: 60, tactics: new Tactics, subsLeft: 3,
    );
}

/**
 * @return array<string, mixed>
 */
function modelAnswers(array $overrides = []): array
{
    $answers = json_decode(file_get_contents(__DIR__.'/../SystemOne/fixtures/decision.json'), true);
    $answers['answers'] = array_replace_recursive($answers['answers'], $overrides);

    return $answers;
}

beforeEach(function (): void {
    config(['manager.ollama_url' => 'http://ollama.test', 'manager.model' => 'd3:2b']);
});

it('sends the match state from its own perspective with every question', function (): void {
    Http::fake(['*' => Http::response(modelAnswers())]);

    app(SystemOneManager::class)->decide(losingLate());

    Http::assertSent(fn (Request $request): bool => ($request->data()['state'] ?? null) === losingLate()->toModelState()
        && ($request->data()['state'] ?? null)['score'] === ['us' => 0, 'them' => 1]
        && array_keys(($request->data()['questions'] ?? null)) === ['mentality', 'press_high', 'concern']);
});

it('maps the answers to a decision with probabilities and concern', function (): void {
    Http::fake(['*' => Http::response(modelAnswers([
        'mentality' => ['choice' => 'attacking'],
        'press_high' => ['noul' => 0.73],
        'concern' => ['score' => 3.4],
    ]))]);

    $decision = app(SystemOneManager::class)->decide(losingLate());

    expect($decision->mentality)->toBe(Mentality::Attacking)
        ->and($decision->pressHigh)->toBeTrue()
        ->and($decision->concern)->toBe(4)
        ->and($decision->fallbackUsed)->toBeFalse()
        ->and($decision->responseMs)->toBeInt()
        ->and($decision->probabilities['mentality']['balanced'])->toEqualWithDelta(0.5208, 0.0001)
        ->and($decision->probabilities['press_high']['true'])->toEqualWithDelta(0.73, 0.0001)
        ->and($decision->probabilities['press_high']['false'])->toEqualWithDelta(0.27, 0.0001)
        ->and($decision->probabilities['concern'])->toHaveCount(5);
});

it('does not press when the model says no', function (): void {
    Http::fake(['*' => Http::response(modelAnswers(['press_high' => ['noul' => 0.49]]))]);

    expect(app(SystemOneManager::class)->decide(losingLate())->pressHigh)->toBeFalse();
});

it('keeps concern between 1 and 5', function (float $score, int $concern): void {
    Http::fake(['*' => Http::response(modelAnswers(['concern' => ['score' => $score]]))]);

    expect(app(SystemOneManager::class)->decide(losingLate())->concern)->toBe($concern);
})->with([[0.0, 1], [0.4, 1], [2.5, 4], [4.0, 5], [9.0, 5]]);

it('falls back to the rule-based manager when the model times out', function (): void {
    Log::spy();
    Http::fake(fn () => throw new ConnectionException('Operation timed out after 30001 milliseconds'));

    $decision = app(SystemOneManager::class)->decide(losingLate());

    // Rule-based: losing after 80 minutes goes all out.
    expect($decision->mentality)->toBe(Mentality::AllOutAttack)
        ->and($decision->fallbackUsed)->toBeTrue()
        ->and($decision->probabilities)->toBeNull()
        ->and($decision->concern)->toBeNull();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($context['reason'], 'timed out')
        && $context['minute'] === "82'"
        && $context['model'] === 'd3:2b')->once();
});

it('falls back on an HTTP error', function (): void {
    Http::fake(['*' => Http::response(['error' => "model 'd3:2b' not found"], 404)]);

    expect(app(SystemOneManager::class)->decide(losingLate())->fallbackUsed)->toBeTrue();
});

it('falls back on malformed answers', function (array $response): void {
    Http::fake(['*' => Http::response($response)]);

    expect(app(SystemOneManager::class)->decide(losingLate())->fallbackUsed)->toBeTrue();
})->with([
    'no answers' => [['model' => 'd3:2b']],
    'missing question' => [['answers' => ['mentality' => modelAnswers()['answers']['mentality']]]],
    'unknown mentality' => [modelAnswers(['mentality' => ['choice' => 'gegenpress']])],
    'noul as text' => [modelAnswers(['press_high' => ['noul' => 'yes']])],
]);

it('falls back on a response that is not JSON', function (): void {
    Http::fake(['*' => Http::response('<html>Bad gateway</html>', 200)]);

    expect(app(SystemOneManager::class)->decide(losingLate())->fallbackUsed)->toBeTrue()
        ->and(app(SystemOneManager::class)->model())->toBe('d3:2b');
});
