<?php

declare(strict_types=1);

use App\SystemOne\SystemOneClient;
use App\SystemOne\SystemOneException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['manager.ollama_url' => 'http://ollama.test:11434', 'manager.model' => 'd3:2b', 'manager.timeout' => 30]);
});

it('sends the model, the JSON state and every configured question in one request', function (): void {
    Http::fake(['ollama.test:11434/v1/systemone' => Http::response(['answers' => []])]);

    $state = ['minute' => 72, 'score' => ['us' => 1, 'them' => 1]];

    app(SystemOneClient::class)->ask($state, config('manager.questions'));

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($state): bool {
        $body = $request->data();

        return $request->url() === 'http://ollama.test:11434/v1/systemone'
            && $request->method() === 'POST'
            && $body['model'] === 'd3:2b'
            && $body['state'] === $state
            && array_keys($body['questions']) === ['mentality', 'press_high', 'substitution', 'concern'];
    });
});

it('builds questions in the verified API shapes', function (): void {
    $questions = config('manager.questions');

    expect($questions['mentality']['type'])->toBe('choice')
        ->and(array_keys($questions['mentality']['criteria']))
        ->toBe(['park_the_bus', 'defensive', 'balanced', 'attacking', 'all_out_attack'])
        ->and($questions['press_high']['type'])->toBe('noul')
        ->and(array_keys($questions['press_high']['criteria']))->toBe(['true', 'false'])
        ->and($questions['concern']['type'])->toBe('score')
        ->and($questions['concern']['criteria'])->toBeList()->toHaveCount(5);
});

it('returns typed answers and how long the model took', function (): void {
    Http::fake(['*' => Http::response(json_decode(file_get_contents(__DIR__.'/fixtures/decision.json'), true))]);

    $answers = app(SystemOneClient::class)->ask(['minute' => 88], config('manager.questions'));

    expect($answers->choice('mentality'))->toBe('balanced')
        ->and($answers->probabilities('mentality'))->toHaveKeys(['park_the_bus', 'all_out_attack'])
        ->and($answers->probabilities('mentality')['balanced'])->toEqualWithDelta(0.5208, 0.0001)
        ->and($answers->noul('press_high'))->toEqualWithDelta(0.0868, 0.0001)
        ->and($answers->score('concern'))->toEqualWithDelta(0.1667, 0.0001)
        ->and($answers->probabilities('concern'))->toHaveKeys(['0', '4'])
        ->and($answers->responseMs)->toBeGreaterThanOrEqual(0)
        ->and(app(SystemOneClient::class)->model())->toBe('d3:2b');
});

it('throws when Ollama cannot be reached', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    app(SystemOneClient::class)->ask('state', []);
})->throws(SystemOneException::class, 'Could not reach Ollama at http://ollama.test:11434');

it('throws with the Ollama error message on an HTTP error', function (): void {
    Http::fake(['*' => Http::response(['error' => "model 'd3:2b' not found"], 404)]);

    app(SystemOneClient::class)->ask('state', []);
})->throws(SystemOneException::class, "Ollama returned HTTP 404: model 'd3:2b' not found");

it('throws on an HTTP error without a JSON body', function (): void {
    Http::fake(['*' => Http::response('Bad gateway', 502)]);

    app(SystemOneClient::class)->ask('state', []);
})->throws(SystemOneException::class, 'Ollama returned HTTP 502: Bad gateway');

it('throws when the response has no answers', function (): void {
    Http::fake(['*' => Http::response('not json at all')]);

    app(SystemOneClient::class)->ask('state', []);
})->throws(SystemOneException::class, 'no answers object');

it('warms up a model Ollama has unloaded with a tiny question', function (): void {
    Http::fake([
        '*/api/ps' => Http::response(['models' => [['name' => 'llama3.2:latest']]]),
        '*/v1/systemone' => Http::response(['answers' => ['ready' => ['type' => 'noul', 'noul' => 0.9]]]),
    ]);

    app(SystemOneClient::class)->warmUp();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/systemone')
        && ($request->data()['model'] ?? null) === 'd3:2b'
        && array_keys(($request->data()['questions'] ?? null)) === ['ready']);
});

it('skips the warm up when the model is already loaded', function (): void {
    Http::fake(['*/api/ps' => Http::response(['models' => [['name' => 'd3:2b']]])]);

    app(SystemOneClient::class)->warmUp();

    Http::assertSentCount(1);
});

it('skips the warm up when Ollama cannot say what is loaded', function (): void {
    Http::fake(['*/api/ps' => Http::response('nope', 500)]);

    app(SystemOneClient::class)->warmUp();

    Http::assertSentCount(1);
});

it('ignores a warm up that cannot reach Ollama', function (): void {
    Http::fake(fn () => throw new ConnectionException('refused'));

    app(SystemOneClient::class)->warmUp();

    Http::assertSentCount(0);
});
