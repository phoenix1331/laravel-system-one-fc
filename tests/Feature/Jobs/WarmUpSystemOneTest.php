<?php

declare(strict_types=1);

use App\Jobs\WarmUpSystemOne;
use App\SystemOne\SystemOneClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('loads the chosen model when Ollama has it asleep', function (): void {
    Http::fake([
        '*/api/ps' => Http::response(['models' => []]),
        '*/v1/systemone' => Http::response(['answers' => []]),
    ]);

    (new WarmUpSystemOne('d3:4b'))->handle(app(SystemOneClient::class));

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/systemone')
        && $request->data()['model'] === 'd3:4b');
});

it('leaves a loaded model alone', function (): void {
    Http::fake(['*/api/ps' => Http::response(['models' => [['name' => 'd3:2b']]])]);

    (new WarmUpSystemOne('d3:2b'))->handle(app(SystemOneClient::class));

    Http::assertSentCount(1);
    expect((new WarmUpSystemOne('d3:2b'))->tries)->toBe(1);
});
